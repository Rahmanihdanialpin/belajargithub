<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function index()
    {
        // Mengambil semua produk yang siap dijual di kasir
        $products = Product::with('ingredients')->get();
        return view('admin.pos.index', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,qris',
            'amount_paid' => 'required|numeric|min:0',
        ]);

        // Gunakan DB Transaction agar jika di tengah jalan stok bahan baku kurang, transaksi dibatalkan total
        return DB::transaction(function () use ($request) {
            $totalAmount = 0;
            $orderItemsData = [];

            // 1. Hitung total harga dan siapkan data item pesanan
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                ];
            }

            // Jika pembayarannya menggunakan QRIS, abaikan input kasir dan langsung set uang pas
            if ($request->payment_method === 'qris') {
                $request->merge(['amount_paid' => $totalAmount]);
            }

            $changeAmount = $request->amount_paid - $totalAmount;
            if ($changeAmount < 0 && $request->payment_method === 'cash') {
                return back()->with('error', 'Uang pembayaran kurang!');
            }

            // 2. Buat data Order POS (Status Langsung Completed)
            $order = Order::create([
                'user_id' => null, // Pembeli offline umumnya tidak login akun
                'customer_name' => $request->input('customer_name', 'Pelanggan Offline'),
                'customer_phone' => $request->input('customer_phone', '-'),
                'customer_address' => 'Pembelian di Toko (POS)',
                'total_amount' => $totalAmount,
                'status' => 'completed', // Transaksi langsung selesai
                
                // 🛠️ PERBAIKAN DI SINI: Mengubah 'channel' menjadi 'source' sesuai struktur Model & Migrasi
                'source' => 'pos', 
                
                'cashier_id' => auth()->id(),
                'payment_method' => $request->payment_method,
                'amount_paid' => $request->amount_paid,
                'change_amount' => max(0, $changeAmount),
                'completed_at' => now(),
            ]);

            // 3. Simpan Detail Item dan POTONG Stok Bahan Baku secara real-time
            foreach ($orderItemsData as $itemData) {
                $itemData['order_id'] = $order->id;
                OrderItem::create($itemData);

                // Pengurangan stok bahan resep dessert
                $product = Product::find($itemData['product_id']);
                if ($product) {
                    foreach ($product->ingredients as $ingredient) {
                        $totalDeduction = $ingredient->pivot->quantity_needed * $itemData['quantity'];
                        $ingredient->decrement('stock', $totalDeduction);
                    }
                }
            }

            return redirect()->route('admin.pos.index')->with('success', 'Transaksi POS Berhasil! ID Nota: #' . $order->id);
        });
    }
}