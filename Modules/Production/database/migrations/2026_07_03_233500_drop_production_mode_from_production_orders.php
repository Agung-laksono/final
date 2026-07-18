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
        Schema::table('production_orders', function (Blueprint $table) {
            if (Schema::hasColumn('production_orders', 'production_mode')) {
                $table->dropColumn('production_mode');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('production_orders', 'production_mode')) {
                $table->enum('production_mode', ['internal', 'maklon'])->default('internal')->after('reference_number');
            }
        });
    }
};
