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
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // admin, member
            $table->timestamps();
            
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('workspace_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('color')->default('gray');
            $table->integer('position')->default(0);
            $table->timestamps();
        });
        
        // Rename marketing_projects to tasks and restructure
        Schema::rename('marketing_projects', 'tasks');
        
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_column_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Drop old status column
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropForeign(['workspace_column_id']);
            $table->dropColumn('workspace_id');
            $table->dropColumn('workspace_column_id');
            $table->string('status')->default('ide_backlog');
        });
        
        Schema::rename('tasks', 'marketing_projects');
        
        Schema::dropIfExists('workspace_columns');
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }
};
