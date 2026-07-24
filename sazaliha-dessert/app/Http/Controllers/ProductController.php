<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Ingredient; 
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->latest();

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->active()->paginate(12);
        $categories = Category::all();

        return view('products.index', compact('products', 'categories'));
    }

    public function show($slug)
    {
        $product = Product::with('category')->where('slug', $slug)->firstOrFail();
        $related = Product::active()->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)->get();

        return view('products.show', compact('product', 'related'));
    }

    public function adminIndex()
    {
        $products = Product::with('category')->latest()->paginate(15);
        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::all();
        $ingredients = Ingredient::all(); 
        
        return view('admin.products.create', compact('categories', 'ingredients'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
            
            'ingredients' => 'required|array|min:1',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity_needed' => 'required|numeric|min:0.01',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();
        $validated['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        // --- 🛠️ PROSES HITUNG OTOMATIS HARGA MODAL (STORE) ---
        $calculatedCostPrice = 0;
        $recipeData = [];
        
        foreach ($validated['ingredients'] as $item) {
            $ingredientMaster = Ingredient::find($item['ingredient_id']);
            if ($ingredientMaster) {
                // 🛠️ PERBAIKAN DI SINI: Ganti ->price menjadi ->cost_per_unit
                $calculatedCostPrice += $item['quantity_needed'] * ($ingredientMaster->cost_per_unit ?? 0);
            }

            $recipeData[$item['ingredient_id']] = [
                'quantity_needed' => $item['quantity_needed']
            ];
        }

        $product = Product::create([
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'cost_price' => $calculatedCostPrice,
            'image' => $validated['image'] ?? null,
            'slug' => $validated['slug'],
            'is_active' => $validated['is_active'],
        ]);

        $product->ingredients()->attach($recipeData);

        return redirect()->route('admin.products.index')->with('success', 'Produk dan resep berhasil ditambahkan!');
    }

    public function edit(Product $product)
    {
        $categories = Category::all();
        $ingredients = Ingredient::all();
        
        // 1. Eager load relasi bahan baku (resep) agar bisa membaca data pivot
        $product->load('ingredients'); 

        // 2. 🛠️ HITUNG HARGA MODAL SECARA ON-THE-FLY BERDASARKAN RESEP NYATA
        $calculatedCostPrice = 0;
        foreach ($product->ingredients as $ingredient) {
            // pivot->quantity_needed = takaran resep dari tabel pivot
            $takaran = $ingredient->pivot->quantity_needed ?? 0;
            
            // cost_per_unit = harga beli bahan baku dari tabel master ingredients
            $hargaBahan = $ingredient->cost_per_unit ?? 0;
            
            // Rumus: Takaran x Harga Bahan
            $calculatedCostPrice += ($takaran * $hargaBahan);
        }

        // Bulatkan nilainya agar rapi saat tampil di input form harganya
        $calculatedCostPrice = round($calculatedCostPrice);
        
        // 3. Oper variabel $calculatedCostPrice ke dalam View Edit
        return view('admin.products.edit', compact('product', 'categories', 'ingredients', 'calculatedCostPrice'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
            
            'ingredients' => 'nullable|array',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity_needed' => 'required|numeric|min:0.01',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            if ($product->image && \Storage::disk('public')->exists($product->image)) {
                \Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        // --- 🛠️ PROSES HITUNG ULANG HARGA MODAL (UPDATE) ---
        $calculatedCostPrice = 0;
        $recipeData = [];
        
        if ($request->has('ingredients')) {
            foreach ($request->ingredients as $item) {
                $ingredientMaster = Ingredient::find($item['ingredient_id']);
                if ($ingredientMaster) {
                    // 🛠️ PERBAIKAN DI SINI: Ganti ->price menjadi ->cost_per_unit
                    $calculatedCostPrice += $item['quantity_needed'] * ($ingredientMaster->cost_per_unit ?? 0);
                }

                $recipeData[$item['ingredient_id']] = [
                    'quantity_needed' => $item['quantity_needed']
                ];
            }
        }

        $validated['cost_price'] = $calculatedCostPrice;

        $product->update($validated);
        $product->ingredients()->sync($recipeData);

        return redirect()->route('admin.products.index')->with('success', 'Produk dan resep berhasil diperbarui!');
    }

    public function destroy(Product $product)
    {
        $product->ingredients()->detach();
        
        if ($product->image && \Storage::disk('public')->exists($product->image)) {
            \Storage::disk('public')->delete($product->image);
        }
        
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil dihapus!');
    }
}