<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        $reviews = auth()->user()->reviews()->with('product')->latest()->get();
        $pendingReviews = Order::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->whereDoesntHave('reviews', fn($q) => $q->where('user_id', auth()->id()))
            ->with('items.product')
            ->latest()
            ->get();

        return view('reviews.index', compact('reviews', 'pendingReviews'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'order_id' => 'nullable|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $validated['user_id'] = auth()->id();

        Review::create($validated);

        return back()->with('success', 'Ulasan berhasil dikirim.');
    }

    // 🚀 FITUR BARU: Halaman khusus Admin untuk melihat semua ulasan pelanggan Sazaliha Dessert
    public function adminIndex()
    {
        // Mengambil semua ulasan beserta data user dan produk terkait menggunakan pagination
        $reviews = Review::with(['user', 'product'])->latest()->paginate(15);

        // Statistik ringkas untuk indikator dashboard ulasan
        $totalReviews = Review::count();
        $averageRating = round(Review::avg('rating'), 1) ?? 0;

        return view('admin.reviews.index', compact('reviews', 'totalReviews', 'averageRating'));
    }

    // 🛠️ MODIFIKASI: Mengizinkan Pemilik Ulasan ATAU Admin untuk menghapus ulasan
    public function destroy(Review $review)
    {
        $user = auth()->user();

        // Ulasan boleh dihapus jika: user tersebut adalah pemilik ulasan ATAU user memiliki akses admin
        if ($review->user_id === $user->id || $user->hasAdminAccess()) {
            $review->delete();
            
            // Redirect cerdas berdasarkan siapa yang menghapus
            if ($user->hasAdminAccess()) {
                return redirect()->route('admin.reviews.index')->with('success', 'Ulasan pelanggan berhasil dimoderasi/dihapus.');
            }
            
            return back()->with('success', 'Ulasan dihapus.');
        }

        abort(403, 'Anda tidak memiliki hak akses untuk menghapus ulasan ini.');
    }
}