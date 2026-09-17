<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            // chunk_index: 0 = header/summary chunk, 1,2,3... = detail chunks
            $table->unsignedTinyInteger('chunk_index')->default(0)->after('model_id');
            $table->string('chunk_type', 50)->default('full')->after('chunk_index'); // 'full', 'header', 'items', 'payment'
        });
        
        // Update unique constraint untuk support chunking
        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            $table->unique(['model_type', 'model_id', 'chunk_index'], 'uq_model_chunk');
        });
    }

    public function down(): void
    {
        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            $table->dropUnique('uq_model_chunk');
            $table->dropColumn(['chunk_index', 'chunk_type']);
        });
    }
};
