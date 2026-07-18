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
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wa_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('fonnte_id')->nullable()->index();
            $table->enum('direction', ['in', 'out']);
            $table->string('message_type')->default('text'); // text, image, document, audio, video
            $table->text('message')->nullable();
            $table->string('media_url')->nullable();
            $table->string('status')->default('pending'); // pending, sent, delivered, read, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
    }
};
