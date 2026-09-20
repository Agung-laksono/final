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
        Schema::rename('marketing_subtasks', 'task_subtasks');
        Schema::rename('marketing_comments', 'task_comments');
        Schema::rename('marketing_attachments', 'task_attachments');
        Schema::rename('marketing_activities', 'task_activities');
        Schema::rename('marketing_time_logs', 'task_time_logs');
        Schema::rename('marketing_project_user', 'task_assignee');
        Schema::rename('marketing_project_label', 'task_label');
        Schema::rename('marketing_labels', 'task_labels');

        // Update foreign key columns
        Schema::table('task_subtasks', function (Blueprint $table) {
            $table->renameColumn('marketing_project_id', 'task_id');
        });
        Schema::table('task_comments', function (Blueprint $table) {
            $table->renameColumn('marketing_project_id', 'task_id');
        });
        Schema::table('task_attachments', function (Blueprint $table) {
            $table->renameColumn('marketing_project_id', 'task_id');
        });
        Schema::table('task_activities', function (Blueprint $table) {
            $table->renameColumn('marketing_project_id', 'task_id');
        });
        Schema::table('task_time_logs', function (Blueprint $table) {
            $table->renameColumn('marketing_project_id', 'task_id');
        });
        Schema::table('task_assignee', function (Blueprint $table) {
            $table->renameColumn('marketing_project_id', 'task_id');
        });
        Schema::table('task_label', function (Blueprint $table) {
            $table->renameColumn('marketing_project_id', 'task_id');
            $table->renameColumn('marketing_label_id', 'task_label_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert foreign key columns
        Schema::table('task_label', function (Blueprint $table) {
            $table->renameColumn('task_id', 'marketing_project_id');
            $table->renameColumn('task_label_id', 'marketing_label_id');
        });
        Schema::table('task_assignee', function (Blueprint $table) {
            $table->renameColumn('task_id', 'marketing_project_id');
        });
        Schema::table('task_time_logs', function (Blueprint $table) {
            $table->renameColumn('task_id', 'marketing_project_id');
        });
        Schema::table('task_activities', function (Blueprint $table) {
            $table->renameColumn('task_id', 'marketing_project_id');
        });
        Schema::table('task_attachments', function (Blueprint $table) {
            $table->renameColumn('task_id', 'marketing_project_id');
        });
        Schema::table('task_comments', function (Blueprint $table) {
            $table->renameColumn('task_id', 'marketing_project_id');
        });
        Schema::table('task_subtasks', function (Blueprint $table) {
            $table->renameColumn('task_id', 'marketing_project_id');
        });

        // Revert table names
        Schema::rename('task_labels', 'marketing_labels');
        Schema::rename('task_label', 'marketing_project_label');
        Schema::rename('task_assignee', 'marketing_project_user');
        Schema::rename('task_time_logs', 'marketing_time_logs');
        Schema::rename('task_activities', 'marketing_activities');
        Schema::rename('task_attachments', 'marketing_attachments');
        Schema::rename('task_comments', 'marketing_comments');
        Schema::rename('task_subtasks', 'marketing_subtasks');
    }
};
