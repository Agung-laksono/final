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
            if (!Schema::hasColumn('sales_orders', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('customer_id');
            }
            if (!Schema::hasColumn('sales_orders', 'recipient_phone')) {
                $table->string('recipient_phone')->nullable()->after('recipient_name');
            }
            if (!Schema::hasColumn('sales_orders', 'shipping_address')) {
                $table->text('shipping_address')->nullable()->after('recipient_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['recipient_name', 'recipient_phone', 'shipping_address']);
        });
    }
};
