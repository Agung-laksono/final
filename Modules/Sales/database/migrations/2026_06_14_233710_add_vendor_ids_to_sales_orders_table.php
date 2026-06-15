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
            $table->foreignId('packing_vendor_id')->nullable()->after('packing_fee')->constrained('vendors')->nullOnDelete();
            $table->foreignId('courier_vendor_id')->nullable()->after('shipping_fee')->constrained('vendors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['packing_vendor_id']);
            $table->dropColumn('packing_vendor_id');
            $table->dropForeign(['courier_vendor_id']);
            $table->dropColumn('courier_vendor_id');
        });
    }
};
