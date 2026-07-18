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
        Schema::table('sales_payments', function (Blueprint $table) {
            $table->renameColumn('proof_image', 'proof_path');
            $table->foreignId('finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
        });

        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->foreignId('finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $table->string('payment_method')->nullable();
            $table->string('proof_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropForeign(['finance_account_id']);
            $table->dropColumn('finance_account_id');
            $table->dropColumn('payment_method');
            $table->dropColumn('proof_path');
        });

        Schema::table('sales_payments', function (Blueprint $table) {
            $table->dropForeign(['finance_account_id']);
            $table->dropColumn('finance_account_id');
            $table->renameColumn('proof_path', 'proof_image');
        });
    }
};
