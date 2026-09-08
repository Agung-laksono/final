<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InternalMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(public ChatMessage $message)
    {
        $message->load('sender');

        $this->payload = [
            'id'              => $message->id,
            'conversation_id' => $message->chat_conversation_id,
            'body'            => $message->body,
            'type'            => $message->type,
            'attachment_url'  => $message->attachment_url,
            'sender_id'       => $message->sender_id,
            'sender_name'     => $message->sender?->name ?? 'Unknown',
            'sender_avatar'   => $message->sender?->avatarUrl() ?? null,
            'created_at'      => $message->created_at->toIso8601String(),
        ];
    }

    /**
     * Broadcast ke:
     * - private-chat.{conversationId} → untuk user yang sedang di dalam room ini (real-time messages)
     * - chat-global → untuk update badge unread di conversation list semua anggota
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->message->chat_conversation_id),
            new Channel('chat-global'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
