<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number', 'midtrans_order_id', 'user_id', 'address_id', 'customer_name', 'customer_phone',
        'customer_email', 'customer_address', 'total_amount',
        'status', 'payment_token', 'payment_status', 'payment_type',
        'notes', 'processed_at', 'completed_at', 'paid_at', 'payment_payload',
        
        // 🛠️ Kolom Tambahan untuk Fitur POS & Integrasi Keuangan
        'source',          // 'online' atau 'pos'
        'cashier_id',      // ID user/staf yang menjaga kasir
        'payment_method',  // 'midtrans', 'cash', 'qris', 'transfer'
        'amount_paid',     // Nominal uang tunai yang diserahkan pembeli (untuk POS)
        'change_amount'    // Nominal kembalian (untuk POS)
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_payload' => 'array',
        
        // Cast nominal uang bayar & kembalian agar presisi tipenya
        'amount_paid' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    /**
     * Relasi ke Staf Kasir (User) yang melayani transaksi POS
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                // Modifikasi prefix otomatis: jika POS gunakan 'INV-POS-', jika online gunakan 'ORD-'
                $prefix = request()->is('*pos*') || ($order->source === 'pos') ? 'INV-POS-' : 'ORD-';
                $order->order_number = $prefix . strtoupper(uniqid());
            }
        });
    }
}