<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('midtrans_order_id')->nullable()->after('order_number')->index();
            $table->string('payment_token')->nullable()->after('midtrans_order_id');
            $table->string('payment_status')->nullable()->after('status')->index();
            $table->string('payment_type')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_type');
            $table->json('payment_payload')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'midtrans_order_id',
                'payment_token',
                'payment_status',
                'payment_type',
                'paid_at',
                'payment_payload',
            ]);
        });
    }
};

