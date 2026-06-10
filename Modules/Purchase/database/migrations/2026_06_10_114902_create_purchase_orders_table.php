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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->date('order_date');
            $table->string('status')->default('draft'); // Diubah jadi string agar fleksibel ditambah/dikurang nanti
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->nullable(); // Ongkir
            $table->decimal('discount', 15, 2)->nullable(); // Diskon Global
            $table->decimal('tax', 15, 2)->nullable(); // Pajak
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
