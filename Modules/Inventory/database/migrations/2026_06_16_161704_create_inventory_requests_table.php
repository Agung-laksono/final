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
        Schema::create('inventory_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('source_type')->nullable(); // e.g. 'sales'
            $table->string('reference_number')->nullable(); // e.g. 'SO-0001'
            $table->integer('requested_qty');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft'); // draft, review, routed, rejected
            $table->string('routed_to')->nullable(); // 'purchase' or 'production'
            $table->foreignId('routed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_requests');
    }
};
