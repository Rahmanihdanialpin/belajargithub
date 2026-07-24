<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        // 1. Ambil data ulasan beserta relasi user dan produk agar tidak N+1 query
        $reviews = Review::with(['user', 'product'])->latest()->paginate(15);
        
        // 2. Hitung statistik yang dibutuhkan oleh view dashboard admin
        $totalReviews = Review::count();
        $averageRating = round(Review::avg('rating'), 1) ?? 0;

        // 3. Kirim semua variabel ke view admin
        return view('admin.reviews.index', compact('reviews', 'totalReviews', 'averageRating')); 
    }

    // Pastikan method destroy juga siap jika admin ingin menghapus ulasan buruk/spam
    public function destroy(Review $review)
    {
        $review->delete();
        return redirect()->route('admin.reviews.index')->with('success', 'Ulasan pelanggan berhasil dihapus!');
    }
}