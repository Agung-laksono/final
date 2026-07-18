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
        Schema::create('finance_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_account_id')->constrained('finance_accounts')->cascadeOnDelete();
            $table->foreignId('to_account_id')->constrained('finance_accounts')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('transfer_date');
            $table->text('notes')->nullable();
            
            // Two-Step Verification fields
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');
            $table->string('proof_path')->nullable(); // Upload file transfer proof
            $table->text('rejection_reason')->nullable();
            
            // Actors
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_transfers');
    }
};
