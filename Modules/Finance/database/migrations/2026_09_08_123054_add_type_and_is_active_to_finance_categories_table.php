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
        Schema::table('finance_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('finance_categories', 'type')) {
                $table->string('type')->default('expense')->after('description'); // income or expense
            }
            if (!Schema::hasColumn('finance_categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_categories', function (Blueprint $table) {
            if (Schema::hasColumn('finance_categories', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('finance_categories', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
