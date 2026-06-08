<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            // Hapus unique constraint agar 1 nomor referensi bisa dipakai untuk banyak barang
            $table->dropUnique(['reference_number']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->unique('reference_number');
        });
    }
};
