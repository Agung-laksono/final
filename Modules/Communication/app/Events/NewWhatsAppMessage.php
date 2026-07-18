<?php

namespace Modules\Communication\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Communication\Models\WaMessage;

class NewWhatsAppMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(WaMessage $message)
    {
        $this->message = $message->load('conversation'); // Load relasi percakapan
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Bisa broadcast global untuk me-refresh list chat,
            // atau channel private spesifik conversation_id untuk view room chat.
            new Channel('wa.conversations'),
            new Channel('wa.conversation.' . $this->message->wa_conversation_id),
        ];
    }
    
    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MessageReceived';
    }
}
