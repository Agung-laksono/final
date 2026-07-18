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
        if (Schema::hasColumn('sales_orders', 'deadline')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('deadline');
            });
        }
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->date('deadline')->nullable()->after('order_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('deadline');
        });
    }
};
