<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = [
            [
                'name' => 'Tepung Terigu',
                'unit' => 'gram',
                'stock' => 5000,
                'min_stock' => 1000,
                'cost_per_unit' => 1200.00,
                'supplier' => 'PT Sari Rasa',
                'notes' => 'Gunakan tepung protein sedang untuk cake dan cookies.',
            ],
            [
                'name' => 'Gula Pasir',
                'unit' => 'gram',
                'stock' => 3000,
                'min_stock' => 500,
                'cost_per_unit' => 800.00,
                'supplier' => 'CV Manis Jaya',
                'notes' => 'Simpan di tempat kering, hindari kelembapan.',
            ],
            [
                'name' => 'Mentega',
                'unit' => 'gram',
                'stock' => 500,
                'min_stock' => 200,
                'cost_per_unit' => 2100.00,
                'supplier' => 'Dairy Fresh',
                'notes' => 'Gunakan mentega tawar untuk rasa lebih netral.',
            ],
            [
                'name' => 'Cokelat Batangan',
                'unit' => 'gram',
                'stock' => 1000,
                'min_stock' => 300,
                'cost_per_unit' => 2500.00,
                'supplier' => 'Cokelat Nusantara',
                'notes' => 'Cokelat compound untuk topping dan leleh.',
            ],
            [
                'name' => 'Susu Full Cream',
                'unit' => 'liter',
                'stock' => 20,
                'min_stock' => 5,
                'cost_per_unit' => 18000.00,
                'supplier' => 'Indo Dairy',
                'notes' => 'Susu cair segar untuk pudding dan adonan cake.',
            ],
            [
                'name' => 'Telur Ayam',
                'unit' => 'pcs',
                'stock' => 120,
                'min_stock' => 30,
                'cost_per_unit' => 2500.00,
                'supplier' => 'Peternakan Mandiri',
                'notes' => 'Telur ukuran sedang, cek kualitas sebelum digunakan.',
            ],
            [
                'name' => 'Baking Powder',
                'unit' => 'gram',
                'stock' => 250,
                'min_stock' => 50,
                'cost_per_unit' => 6500.00,
                'supplier' => 'PT Kimia Adi',
                'notes' => 'Untuk mengembang, simpan di tempat sejuk.',
            ],
            [
                'name' => 'Krim Kocok',
                'unit' => 'liter',
                'stock' => 8,
                'min_stock' => 4,
                'cost_per_unit' => 24000.00,
                'supplier' => 'Creamy Co.',
                'notes' => 'Krim cair siap dikocok untuk topping dessert.',
            ],
            [
                'name' => 'Cokelat Bubuk',
                'unit' => 'gram',
                'stock' => 0,
                'min_stock' => 100,
                'cost_per_unit' => 1900.00,
                'supplier' => 'Choco Powder',
                'notes' => 'Stok habis, segera pesan lagi untuk brownies.',
            ],
            [
                'name' => 'Gula Halus',
                'unit' => 'gram',
                'stock' => 200,
                'min_stock' => 150,
                'cost_per_unit' => 950.00,
                'supplier' => 'CV Manis Jaya',
                'notes' => 'Cocok untuk buttercream dan frosting.',
            ],
        ];

        foreach ($ingredients as $ingredient) {
            Ingredient::updateOrCreate(
                ['name' => $ingredient['name']],
                $ingredient
            );
        }
    }
}
