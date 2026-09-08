<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom initial_balance ke finance_accounts.
     * Kolom ini menyimpan saldo awal saat akun pertama dibuat,
     * sehingga Buku Besar bisa menghitung "Saldo Awal Periode" dengan benar.
     * 
     * Akun lama mendapat default 0 — aman, karena selama ini memang diperlakukan sebagai 0.
     */
    public function up(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->decimal('initial_balance', 15, 2)->default(0)->after('current_balance');
        });
    }

    public function down(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->dropColumn('initial_balance');
        });
    }
};
