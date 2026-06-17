<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KanbanUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $type;

    /**
     * Create a new event instance.
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * Helper aman untuk broadcast menggunakan toOthers tanpa merusak aplikasi jika server realtime mati
     */
    public static function safeDispatch($type)
    {
        try {
            broadcast(new static($type))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Kanban Broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('kanban'),
            new Channel('kanban.' . $this->type),
        ];
    }
}
