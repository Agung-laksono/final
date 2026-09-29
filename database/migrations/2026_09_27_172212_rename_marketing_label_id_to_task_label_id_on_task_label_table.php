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
        if (Schema::hasColumn('task_label', 'marketing_label_id')) {
            Schema::table('task_label', function (Blueprint $table) {
                $table->renameColumn('marketing_label_id', 'task_label_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('task_label', 'task_label_id')) {
            Schema::table('task_label', function (Blueprint $table) {
                $table->renameColumn('task_label_id', 'marketing_label_id');
            });
        }
    }
};
