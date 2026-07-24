<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        // 1. Ambil data produk unik per kategori (Opsi 2 yang kita bahas sebelumnya)
        $featuredProducts = Product::with('category')
            ->where('is_active', 1)
            ->latest()
            ->get()
            ->unique('category_id')
            ->take(8); // Sesuaikan jumlah maksimal card yang ingin ditampilkan

        // 2. DEF INISIKAN VARIABEL YANG HILANG DI SINI:
        $categories = Category::withCount('products')->get();
        
        return view('welcome', compact('featuredProducts', 'categories'));
    }
}
