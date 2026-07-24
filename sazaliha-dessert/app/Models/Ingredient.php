<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
        'stock',
        'min_stock',
        'cost_per_unit',
        'supplier',
        'notes',
    ];

    protected $casts = [
        'stock' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'cost_per_unit' => 'decimal:2',
    ];

    // 🚀 Hubungan ke Produk (Resep/Pivot) jika dibutuhkan
    public function products()
    {
        return $this->belongsToMany(Product::class, 'ingredient_product')
                    ->withPivot('quantity_needed')
                    ->withTimestamps();
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    /**
     * 🚀 ACCESSOR PERBAIKAN: Menghitung Nilai Aset Stok Secara Fleksibel
     */
    public function getStockValueAttribute(): float
    {
        $unitLower = strtolower($this->unit);
        $currentStock = (float) $this->stock;
        $cost = (float) $this->cost_per_unit;

        // Jika admin menginput unit GRAM/ML tapi memasukkan harga basis KG/LITER (Misal: Rp 15.000/kg)
        // Maka harga per gram otomatis dikonversi dibagi 1000 agar matematika keuangan tetap presisi.
        if (in_array($unitLower, ['gram', 'g', 'ml'])) {
            // Cek jika harga yang diinput adalah harga grosir (bukan harga eceran per 1 gram)
            if ($cost > 1000) {
                return $currentStock * ($cost / 1000);
            }
        }

        return $currentStock * $cost;
    }

    public function getStatusAttribute(): string
    {
        if ((float) $this->stock <= 0) {
            return 'habis';
        }
        if ((float) $this->stock <= (float) $this->min_stock) {
            return 'menipis';
        }
        return 'aman';
    }
}