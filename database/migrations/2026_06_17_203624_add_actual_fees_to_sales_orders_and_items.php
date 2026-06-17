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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('actual_packing_fee', 15, 2)->default(0)->after('packing_fee');
            $table->decimal('actual_shipping_fee', 15, 2)->default(0)->after('shipping_fee');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->decimal('actual_packing_fee', 15, 2)->default(0)->after('subtotal');
            $table->decimal('actual_shipping_fee', 15, 2)->default(0)->after('actual_packing_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn(['actual_packing_fee', 'actual_shipping_fee']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['actual_packing_fee', 'actual_shipping_fee']);
        });
    }
};
