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
        Schema::table('sales_order_fulfillments', function (Blueprint $table) {
            $table->foreignId('item_label_id')->nullable()->constrained('item_labels')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_fulfillments', function (Blueprint $table) {
            $table->dropForeign(['item_label_id']);
            $table->dropColumn('item_label_id');
        });
    }
};
