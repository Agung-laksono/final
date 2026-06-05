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
        Schema::create('item_warehouse', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke tabel items dan warehouses
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            
            // Jumlah stok fisik di gudang ini
            $table->integer('stock')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_warehouse');
    }
};
