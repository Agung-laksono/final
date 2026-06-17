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
        Schema::create('production_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_recipe_id')->constrained('production_recipes')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete(); // The raw material
            $table->integer('qty'); // How many of this raw material are needed for 1 finished good
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_recipe_items');
    }
};
