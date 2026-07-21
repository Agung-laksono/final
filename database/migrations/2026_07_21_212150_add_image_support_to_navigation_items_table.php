<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->string('icon_type')->default('flux')->after('label'); // 'flux' or 'image'
            $table->string('image_path')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropColumn(['icon_type', 'image_path']);
        });
    }
};
