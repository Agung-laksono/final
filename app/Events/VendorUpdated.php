<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct($message = 'Data vendor berubah')
    {
        $userName = auth()->check() ? auth()->user()->name : 'Sistem';
        $this->message = $message . ' oleh ' . $userName;
    }

    /**
     * Helper aman untuk broadcast menggunakan toOthers tanpa merusak aplikasi jika server realtime mati
     */
    public static function safeDispatch($message)
    {
        try {
            broadcast(new static($message))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast failed: ' . $e->getMessage());
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
            new Channel('purchase'),
        ];
    }
}
