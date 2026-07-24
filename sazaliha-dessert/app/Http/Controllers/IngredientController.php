<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index()
    {
        $ingredients = Ingredient::latest()->paginate(15);
        $lowStockCount = Ingredient::lowStock()->count();
        return view('admin.ingredients.index', compact('ingredients', 'lowStockCount'));
    }

    public function create()
    {
        $units = ['kg', 'gram', 'liter', 'ml', 'pcs', 'pack', 'box', 'sachet', 'botol'];
        return view('admin.ingredients.create', compact('units'));
    }

    public function store(Request $request)
    {
        // 🚀 Bersihkan pemisah ribuan jika form input menggunakan masking Rupiah
        if ($request->has('cost_per_unit')) {
            $cleanedCost = preg_replace('/[^0-9.]/', '', $request->input('cost_per_unit'));
            $request->merge(['cost_per_unit' => $cleanedCost]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'stock' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'cost_per_unit' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        Ingredient::create($validated);

        return redirect()->route('admin.ingredients.index')->with('success', 'Bahan baku berhasil ditambahkan!');
    }

    public function edit(Ingredient $ingredient)
    {
        $units = ['kg', 'gram', 'liter', 'ml', 'pcs', 'pack', 'box', 'sachet', 'botol'];
        return view('admin.ingredients.edit', compact('ingredient', 'units'));
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        // 🚀 Bersihkan pemisah ribuan saat update data
        if ($request->has('cost_per_unit')) {
            $cleanedCost = preg_replace('/[^0-9.]/', '', $request->input('cost_per_unit'));
            $request->merge(['cost_per_unit' => $cleanedCost]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'stock' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'cost_per_unit' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // 🚀 AMBIL DATA SATUAN SEBELUM DI-UPDATE
        $oldUnit = strtolower($ingredient->unit);
        $newUnit = strtolower($validated['unit']);

        // Jika ada perubahan satuan berat atau volume
        if ($oldUnit !== $newUnit) {
            $stock = (float) $validated['stock'];
            $minStock = (float) $validated['min_stock'];
            $cost = (float) $validated['cost_per_unit'];

            // KONDISI 1: Konversi Satuan Besar (Kg/Liter) ke Satuan Kecil (Gram/Ml)
            if (in_array($oldUnit, ['kg', 'liter']) && in_array($newUnit, ['gram', 'g', 'ml'])) {
                // Jika user mengedit teks input tapi mempertahankan angka basis satuan besar (misal input tetap ditulis 2 kg)
                // Kita amankan dengan mengalikan 1000 agar menjadi gram/ml di database
                if ($stock == (float) $ingredient->stock) {
                    $validated['stock'] = $stock * 1000;
                    $validated['min_stock'] = $minStock * 1000;
                }
                
                // Jika harga per unit masih menggunakan standar harga basis Kg/Liter, bagi 1000
                if ($cost == (float) $ingredient->cost_per_unit && $cost > 1000) {
                    $validated['cost_per_unit'] = $cost / 1000;
                }
            }
            
            // KONDISI 2: Konversi Satuan Kecil (Gram/Ml) ke Satuan Besar (Kg/Liter)
            elseif (in_array($oldUnit, ['gram', 'g', 'ml']) && in_array($newUnit, ['kg', 'liter'])) {
                // Jika angka di form masih sama dengan data lama di database (misal 2000 gram)
                // Kita bagi 1000 agar tersimpan sebagai nilai desimal kg/liter yang benar (2 kg)
                if ($stock == (float) $ingredient->stock) {
                    $validated['stock'] = $stock / 1000;
                    $validated['min_stock'] = $minStock / 1000;
                }
                
                // Sesuaikan harga eceran gram ke harga grosir per Kg/Liter
                if ($cost == (float) $ingredient->cost_per_unit && $cost < 1000) {
                    $validated['cost_per_unit'] = $cost * 1000;
                }
            }
        }

        // Eksekusi update data ke database setelah kalkulasi selesai
        $ingredient->update($validated);

        return redirect()->route('admin.ingredients.index')
            ->with('success', 'Bahan baku berhasil diperbarui dan disesuaikan ke satuan ' . $validated['unit'] . '!');
    }

    public function destroy(Ingredient $ingredient)
    {
        $ingredient->delete();
        return redirect()->route('admin.ingredients.index')->with('success', 'Bahan baku berhasil dihapus!');
    }
}