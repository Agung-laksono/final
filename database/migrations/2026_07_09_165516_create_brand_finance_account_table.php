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
        // Buat tabel pivot many-to-many
        Schema::create('brand_finance_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('finance_account_id')->constrained('finance_accounts')->cascadeOnDelete();
            $table->unique(['brand_id', 'finance_account_id']);
        });

        // Hapus kolom lama di tabel brands
        Schema::table('brands', function (Blueprint $table) {
            $table->dropForeign(['finance_account_id']);
            $table->dropColumn('finance_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_finance_account');
        Schema::table('brands', function (Blueprint $table) {
            $table->foreignId('finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
        });
    }
};
