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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('requested_qty');
            $table->integer('fulfilled_qty')->default(0); // For partial receiving
            $table->string('status')->default('pending_approval'); // pending_approval, material_fulfillment, in_production, receiving, completed, archived
            $table->text('notes')->nullable();
            $table->decimal('vendor_cost', 15, 2)->default(0); // Biaya vendor/maklon
            $table->string('reference_number')->nullable(); // E.g., from InventoryRequest
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
