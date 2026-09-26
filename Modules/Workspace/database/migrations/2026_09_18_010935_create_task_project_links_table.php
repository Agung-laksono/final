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
        Schema::create('task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_project_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('to_project_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('relation_type');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_links');
    }
};
