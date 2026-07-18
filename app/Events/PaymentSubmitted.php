<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct($message = 'Pembayaran baru menunggu validasi')
    {
        $this->message = $message;
    }

    public static function safeDispatch($message = 'Pembayaran baru menunggu validasi')
    {
        try {
            broadcast(new static($message))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Finance Broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('finance'),
        ];
    }
}
