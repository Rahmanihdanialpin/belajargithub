<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewOrderNotification;

class OrderController extends Controller
{
    public function create()
    {
        $cart = Cart::with('items.product')
            ->where('user_id', auth()->id())
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('products.index')->with('error', 'Keranjang masih kosong!');
        }

        $selectedIds = (array) request()->input('selected_cart_item_ids', []);
        $selectedIds = array_values(array_unique(array_filter($selectedIds)));

        if (empty($selectedIds)) {
            return redirect()->route('cart.index')->with('error', 'Pilih dulu item yang akan di checkout, ya.');
        }

        $cartItemsQuery = $cart->items()
            ->whereIn('id', $selectedIds);

        $items = $cartItemsQuery->with('product')->get()->map(function (CartItem $item) {
            $product = $item->product;
            return [
                'product' => $product,
                'quantity' => $item->quantity,
                'subtotal' => $product->price * $item->quantity,
            ];
        })->values()->toArray();

        $total = array_sum(array_column($items, 'subtotal'));

        $addresses = auth()->check()
            ? auth()->user()->addresses()->latest()->get()
            : collect();

        $primaryAddress = $addresses->firstWhere('is_primary', true) ?? $addresses->first();

        return view('order.create', compact('items', 'total', 'addresses', 'primaryAddress'));
    }

    public function store(Request $request)
    {
        $cart = Cart::with('items.product')
            ->where('user_id', auth()->id())
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('products.index')->with('error', 'Keranjang masih kosong!');
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'address_id' => 'nullable|exists:addresses,id',
            'customer_address' => 'required_without:address_id|string|nullable',
            'notes' => 'nullable|string',
        ]);

        $total = 0;
        $orderItems = [];

        $selectedIds = (array) $request->input('selected_cart_item_ids', []);
        $selectedIds = array_values(array_unique(array_filter($selectedIds)));

        if (empty($selectedIds)) {
            return back()->with('error', 'Silakan pilih item keranjang yang akan di checkout.');
        }

        $cartItems = $cart->items()
            ->whereIn('id', $selectedIds)
            ->with('product')
            ->get();

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Silakan pilih item keranjang yang akan di checkout.');
        }

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;

            if (!$product) {
                return back()->with('error', 'Produk tidak ditemukan!');
            }

            // ❌ LOGIKA PENGECEKAN STOK PRODUK JADI ($product->stock) DIHAPUS

            $subtotal = $product->price * $cartItem->quantity;
            $total += $subtotal;

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $cartItem->quantity,
                'price' => $product->price,
                'subtotal' => $subtotal,
            ];
        }

        $validated['total_amount'] = $total;
        $validated['status'] = 'pending';
        $validated['user_id'] = auth()->id();

        $phone = $request->input('customer_phone');
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        $validated['customer_phone'] = $phone;

        if (!empty($validated['address_id'])) {
            $selectedAddress = \App\Models\Address::find($validated['address_id']);
        
            if ($selectedAddress) {
                $addressString = $selectedAddress->full_address . ', ' . $selectedAddress->city;
            
                if ($selectedAddress->postal_code) {
                    $addressString .= ' (' . $selectedAddress->postal_code . ')';
                }
                if ($selectedAddress->notes) {
                    $addressString .= ' - ' . $selectedAddress->notes;
                }
            
                $validated['customer_address'] = $addressString;
            }
        }

        $order = Order::create($validated);

        foreach ($orderItems as $item) {
            $item['order_id'] = $order->id;
            OrderItem::create($item);

            // ❌ LOGIKA PENGURANGAN STOK PRODUK JADI DIHAPUS
            // Karena pemotongan stok bahan baku sudah kamu pasang dengan benar saat status berubah ke 'processing'
        }

        if (!empty($selectedIds)) {
            $cart->items()->whereIn('id', $selectedIds)->delete();
        }

        try {
            if ($order->customer_email) {
                Mail::to($order->customer_email)->send(new \App\Mail\OrderConfirmation($order));
            }
            Mail::to('admin@sazaliha.test')->send(new NewOrderNotification($order));
        } catch (\Exception $e) {
            // Silent fail for email
        }

        return redirect()->route('order.success', $order)->with('success', 'Pesanan berhasil dibuat!');
    }

    public function success(Order $order)
    {
        return view('order.success', compact('order'));
    }

    public function adminIndex(Request $request)
    {
        $query = Order::with('items.product')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $orders = $query->paginate(15);
        $pendingCount = Order::where('status', 'pending')->count();
        $processingCount = Order::where('status', 'processing')->count();
        $completedCount = Order::where('status', 'completed')->count();

        return view('admin.orders.index', compact('orders', 'pendingCount', 'processingCount', 'completedCount'));
    }

    public function adminShow(Order $order)
    {
        $order->load('items.product', 'address');
        return view('admin.orders.show', compact('order'));
    }

    public function customerShow(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses ke pesanan ini.');
        }

        $order->load('items.product', 'address');
        return view('order.show', compact('order'));
    }

    public function customerCancel(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses ke pesanan ini.');
        }

        if ($order->status === 'completed') {
            return back()->with('error', 'Pesanan yang sudah selesai tidak dapat dibatalkan.');
        }

        if ($order->status !== 'pending') {
            return back()->with('error', 'Pesanan yang sudah diproses tidak dapat dibatalkan.');
        }

        // ❌ LOGIKA PENGEMBALIAN STOK PRODUK DIHAPUS

        $order->status = 'cancelled';
        $order->save();

        return redirect()->route('dashboard')->with('success', 'Pesanan berhasil dibatalkan.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        if ($order->status === 'completed' || $order->status === 'cancelled') {
            return back()->with('error', 'Pesanan yang sudah selesai/dibatalkan tidak dapat diubah.');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);

        if ($validated['status'] === 'processing' && $order->status === 'pending') {
            $order->processed_at = now();

            foreach ($order->items as $item) {
                $product = $item->product;

                if ($product) {
                    foreach ($product->ingredients as $ingredient) {
                        $totalDeduction = $ingredient->pivot->quantity_needed * $item->quantity;
                        $ingredient->decrement('stock', $totalDeduction);
                    }
                }
            }
        }

        if ($validated['status'] === 'cancelled' && in_array($order->status, ['pending', 'processing'])) {
            // 🚀 LOGIKA BARU: Jika status dibatalkan saat posisinya sudah 'processing', kembalikan stok bahan bakunya!
            if ($order->status === 'processing') {
                foreach ($order->items as $item) {
                    $product = $item->product;
                    if ($product) {
                        foreach ($product->ingredients as $ingredient) {
                            $totalReturn = $ingredient->pivot->quantity_needed * $item->quantity;
                            $ingredient->increment('stock', $totalReturn);
                        }
                    }
                }
            }
        }

        if ($validated['status'] === 'completed' && $order->status !== 'completed') {
            $order->completed_at = now();
        }

        $order->status = $validated['status'];
        $order->save();

        return redirect()->route('admin.orders.show', $order->id)
            ->with('success', 'Status pesanan berhasil diperbarui dan stok bahan baku telah disesuaikan!');
    }

    public function destroy(Order $order)
    {
        if ($order->status !== 'cancelled') {
            return back()->with('error', 'Hanya pesanan yang sudah dibatalkan yang dapat dihapus.');
        }

        $order->items()->delete();
        $order->delete();

        if (auth()->user()->is_admin) {
            return redirect()->route('admin.dashboard')->with('success', 'Pesanan berhasil dihapus.');
        }

        return redirect()->route('dashboard')->with('success', 'Pesanan berhasil dihapus.');
    }

    public function customerOrders(Request $request)
    {
        $status = $request->get('status', 'all');
        $query = Order::where('user_id', auth()->id())->latest();

        if ($status !== 'all' && in_array($status, ['pending', 'processing', 'completed', 'cancelled'])) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(10)->withQueryString();

        $counts = [
            'all' => Order::where('user_id', auth()->id())->count(),
            'pending' => Order::where('user_id', auth()->id())->where('status', 'pending')->count(),
            'processing' => Order::where('user_id', auth()->id())->where('status', 'processing')->count(),
            'completed' => Order::where('user_id', auth()->id())->where('status', 'completed')->count(),
            'cancelled' => Order::where('user_id', auth()->id())->where('status', 'cancelled')->count(),
        ];

        return view('orders.index', compact('orders', 'status', 'counts'));
    }
}