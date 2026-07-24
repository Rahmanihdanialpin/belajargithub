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
            // 1. Indikator Transaksi (Online vs POS)
            if (!Schema::hasColumn('orders', 'source')) {
                // Menggunakan 'source' agar sinkron dengan ReportController & Blade yang kita buat
                $table->enum('source', ['online', 'pos'])->default('online')->after('user_id');
            }
            
            // 2. Relasi Staf Kasir
            if (!Schema::hasColumn('orders', 'cashier_id')) {
                $table->foreignId('cashier_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            
            // 3. Metode Pembayaran
            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->default('midtrans')->after('status'); // default e-commerce 'midtrans', kasir bisa 'cash'/'qris'
            }
            
            // 4. Nominal Bayar & Kembalian (Kebutuhan POS Kasir)
            if (!Schema::hasColumn('orders', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->nullable()->after('payment_method');
            }
            
            if (!Schema::hasColumn('orders', 'change_amount')) {
                $table->decimal('change_amount', 12, 2)->nullable()->after('amount_paid');
            }

            // 5. Ubah kolom pembeli menjadi nullable (Sebab pembeli offline/walk-in tidak wajib input data)
            $table->string('customer_name')->nullable()->change();
            $table->string('customer_phone')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Hapus foreign key terlebih dahulu sebelum drop kolom cashier_id
            if (Schema::hasColumn('orders', 'cashier_id')) {
                $table->dropForeign(['cashier_id']);
                $table->dropColumn('cashier_id');
            }

            // List kolom bentukan POS yang aman dihapus jika rollback
            $columnsToDrop = array_filter(['source', 'payment_method', 'amount_paid', 'change_amount'], function($column) {
                return Schema::hasColumn('orders', $column);
            });

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
            
            // Kembalikan kolom pembeli menjadi wajib diisi (NOT NULL) jika di-rollback
            $table->string('customer_name')->nullable(false)->change();
            $table->string('customer_phone')->nullable(false)->change();
        });
    }
};