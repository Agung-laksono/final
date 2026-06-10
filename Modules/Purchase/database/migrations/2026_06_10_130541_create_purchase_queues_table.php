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
        Schema::create('purchase_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->integer('requested_qty');
            $table->integer('approved_qty')->nullable();
            $table->integer('ordered_qty')->default(0);
            $table->string('status')->default('pending_approval');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_queues');
    }
};
