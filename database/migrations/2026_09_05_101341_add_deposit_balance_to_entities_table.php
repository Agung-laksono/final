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
        Schema::table('vendors', function (Blueprint $table) {
            $table->decimal('deposit_balance', 15, 2)->default(0)->after('status');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('deposit_balance', 15, 2)->default(0)->after('status');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->decimal('deposit_balance', 15, 2)->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('deposit_balance');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('deposit_balance');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('deposit_balance');
        });
    }
};
