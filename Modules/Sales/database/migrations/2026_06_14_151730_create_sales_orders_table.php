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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('so_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->date('order_date');
            
            // Kolom Status Terpadu
            $table->string('status')->default('pending_approval'); // pending_approval, processing, packing, shipping, completed, rejected
            $table->string('payment_status')->default('unpaid'); // unpaid, partial, paid
            
            // Rincian Biaya
            $table->decimal('packing_fee', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->default(0); // Ongkir
            $table->decimal('discount', 15, 2)->default(0); // Diskon Global
            $table->decimal('tax', 15, 2)->default(0); // Pajak
            $table->decimal('total_amount', 15, 2)->default(0); // Grand total
            
            // Ekspedisi
            $table->string('courier_vendor')->nullable(); // JNE, J&T, etc.
            $table->string('tracking_number')->nullable(); // Resi
            
            // Tracking User & Catatan
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
