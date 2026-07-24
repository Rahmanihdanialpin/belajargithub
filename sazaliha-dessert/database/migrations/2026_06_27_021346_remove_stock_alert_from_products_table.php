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
        Schema::table('products', function (Blueprint $table) {
            // Cek terlebih dahulu apakah kolom stock_alert ada, jika ada maka hapus
            if (Schema::hasColumn('products', 'stock_alert')) {
                $table->dropColumn('stock_alert');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Logika untuk mengembalikan kolom jika migrasi di-rollback (opsional)
            $table->integer('stock_alert')->default(0)->after('cost_price');
        });
    }
};