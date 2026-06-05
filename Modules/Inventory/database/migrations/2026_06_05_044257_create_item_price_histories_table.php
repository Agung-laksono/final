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
        Schema::create('item_price_histories', function (Blueprint $table) {
            $table->id();
            
            // Menginduk ke barang mana?
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            
            // Mencatat harga pada saat diubah
            $table->decimal('purchase_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            
            // (Opsional) Mencatat siapa user yang mengubah harganya
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Timestamp otomatis mencatat 'kapan' harga ini diubah
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_price_histories');
    }
};
