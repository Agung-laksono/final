<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('merged_into_po_id')->nullable()->after('notes')
                  ->constrained('purchase_orders')->nullOnDelete();
            $table->string('status_before_merge')->nullable()->after('merged_into_po_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('original_po_id')->nullable()->after('purchase_order_id');
        });

        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('original_po_id')->nullable()->after('purchase_order_id');
        });
    }

    public function down(): void {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['merged_into_po_id']);
            $table->dropColumn(['merged_into_po_id', 'status_before_merge']);
        });
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('original_po_id');
        });
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropColumn('original_po_id');
        });
    }
};
