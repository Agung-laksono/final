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
        Schema::table('item_warehouse', function (Blueprint $table) {
            $table->decimal('allocated_qty', 10, 2)->default(0)->after('stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_warehouse', function (Blueprint $table) {
            $table->dropColumn('allocated_qty');
        });
    }
};
