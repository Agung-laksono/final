<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('period')->default('Q1'); // Q1, Q2, Q3, Q4, annual
            $table->unsignedSmallInteger('year')->default(date('Y'));
            $table->enum('status', ['draft', 'on_track', 'at_risk', 'achieved', 'failed'])->default('draft');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['numeric', 'percentage', 'boolean'])->default('numeric');
            $table->decimal('start_value', 15, 2)->default(0);
            $table->decimal('target_value', 15, 2)->default(100);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->string('unit')->nullable(); // e.g., "pelanggan", "%", "juta Rp"
            $table->timestamps();
        });

        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('key_result_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('formula')->nullable(); // descriptive formula
            $table->decimal('target_value', 15, 2)->default(100);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->string('unit')->nullable();
            $table->string('period')->default('monthly'); // daily, weekly, monthly, quarterly, annual
            $table->enum('trend', ['up_is_better', 'down_is_better'])->default('up_is_better');
            $table->timestamps();
        });

        // Pivot: connect tasks to key results
        Schema::create('key_result_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->decimal('contribution', 10, 2)->default(1); // berapa besar task ini berkontribusi ke KR
            $table->timestamps();

            $table->unique(['key_result_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_result_task');
        Schema::dropIfExists('kpis');
        Schema::dropIfExists('key_results');
        Schema::dropIfExists('objectives');
    }
};
