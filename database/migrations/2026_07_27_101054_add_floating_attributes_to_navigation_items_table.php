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
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->string('sub_group')->nullable()->after('section');
            $table->string('badge_type')->nullable()->after('sub_group');
            $table->integer('menu_column')->default(1)->after('badge_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropColumn(['sub_group', 'badge_type', 'menu_column']);
        });
    }
};
