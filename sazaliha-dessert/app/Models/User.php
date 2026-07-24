<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

// 1. Tambahkan 'birth_date' ke dalam attribute Fillable di bawah ini
#[Fillable([
    'name', 
    'email', 
    'password', 
    'is_admin', 
    'avatar',
    'phone',
    'gender',      
    'birth_date', // 👈 WAJIB DITAMBAHKAN DI SINI
    'address',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super admin');
    }

    public function hasAdminAccess(): bool
    {
        return (bool) $this->is_admin || $this->hasRole('admin') || $this->isSuperAdmin();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date', // 👈 2. CAST KE DATE agar otomatis terbaca sebagai objek Carbon/Tanggal murni
        ];
    }
}