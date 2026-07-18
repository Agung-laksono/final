<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationsRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $unreadCount
    ) {}

    /**
     * Kirim event ke private channel milik user yang sama.
     * toOthers() akan dikecualikan agar tab yang memicu tidak menerima kembali.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->userId),
        ];
    }

    /**
     * Nama event yang digunakan di frontend (Laravel Echo).
     */
    public function broadcastAs(): string
    {
        return 'NotificationsRead';
    }

    public function broadcastWith(): array
    {
        return [
            'unreadCount' => $this->unreadCount,
        ];
    }
}
