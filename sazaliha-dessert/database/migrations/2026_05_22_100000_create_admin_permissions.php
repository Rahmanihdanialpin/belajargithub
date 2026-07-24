<?php

use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        Schema::create('admin_feature_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('resource'); // e.g. ingredients, products, orders, reports
            $table->boolean('can_create')->default(false);
            $table->boolean('can_read')->default(true);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();

            $table->unique('resource');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_feature_permissions');
    }
};

