<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
// 🚀 TAMBAHKAN BARIS INI:
use Illuminate\Support\Facades\DB; 

class ProductIngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('product_ingredient')->insert([
            [
                'product_id' => 1,      // ID Produk (misal: Dessert Box Chocolate)
                'ingredient_id' => 1,   // ID Bahan Baku (misal: Cokelat Batang)
                'quantity_needed' => 0.5, // Butuh 0.5 kg atau bungkus per box
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => 1,
                'ingredient_id' => 2,   // ID Bahan Baku (misal: Tepung)
                'quantity_needed' => 0.2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}