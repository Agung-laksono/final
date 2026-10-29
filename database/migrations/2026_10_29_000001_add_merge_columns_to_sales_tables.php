<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('merged_into_so_id')->nullable()->after('notes')
                  ->constrained('sales_orders')->nullOnDelete();
            $table->string('status_before_merge')->nullable()->after('merged_into_so_id');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('original_so_id')->nullable()->after('sales_order_id');
        });

        Schema::table('sales_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('original_so_id')->nullable()->after('sales_order_id');
        });
    }

    public function down(): void {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['merged_into_so_id']);
            $table->dropColumn(['merged_into_so_id', 'status_before_merge']);
        });
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('original_so_id');
        });
        Schema::table('sales_payments', function (Blueprint $table) {
            $table->dropColumn('original_so_id');
        });
    }
};
