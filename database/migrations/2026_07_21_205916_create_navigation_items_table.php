<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->string('route_name')->unique();
            $table->string('label');
            $table->string('icon')->default('link');
            $table->string('section')->default('LAINNYA'); // INVENTORY, PEMBELIAN, dll
            $table->integer('sort_order')->default(99);
            $table->string('permission')->nullable(); // e.g. 'inventory.view'
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
