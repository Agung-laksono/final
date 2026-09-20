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
        Schema::create('marketing_project_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_project_id')->constrained('marketing_projects')->cascadeOnDelete();
            $table->foreignId('to_project_id')->constrained('marketing_projects')->cascadeOnDelete();
            $table->string('relation_type');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_project_links');
    }
};
