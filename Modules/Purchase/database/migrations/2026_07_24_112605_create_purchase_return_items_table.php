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
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_receipt_item_id')->nullable()->constrained('purchase_receipt_items')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            
            $table->integer('quantity');
            $table->string('condition')->default('damaged'); // good, damaged, wrong_item
            $table->string('action_requested')->default('refund'); // refund, replacement
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
    }
};
