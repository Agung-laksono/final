<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->default('direct'); // 'direct' | 'group'
            $table->string('name')->nullable();         // Nama group (null untuk direct)
            $table->uuid('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        // Pivot table: anggota conversation
        Schema::create('chat_conversation_user', function (Blueprint $table) {
            $table->id();
            $table->uuid('chat_conversation_id');
            $table->foreign('chat_conversation_id')->references('id')->on('chat_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->unique(['chat_conversation_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversation_user');
        Schema::dropIfExists('chat_conversations');
    }
};
