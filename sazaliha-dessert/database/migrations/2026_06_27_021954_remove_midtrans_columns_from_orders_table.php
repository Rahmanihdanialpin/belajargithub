<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Hapus kolom midtrans_order_id jika ada
            if (Schema::hasColumn('orders', 'midtrans_order_id')) {
                $table->dropColumn('midtrans_order_id');
            }

            // Hapus kolom payment_token si token midtrans jika ada
            if (Schema::hasColumn('orders', 'payment_token')) {
                $table->dropColumn('payment_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Logika rollback untuk mengembalikan kolom jika diperlukan (opsional)
            $table->string('midtrans_order_id')->nullable()->after('order_number');
            $table->string('payment_token')->nullable()->after('status');
        });
    }
};