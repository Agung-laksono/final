<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MenuBadgesUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct() {}

    /**
     * Broadcast to a public channel because badges are dependent on roles,
     * and we want ALL connected clients to re-calculate their own badges.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('global-updates'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MenuBadgesUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'timestamp' => now()->timestamp,
        ];
    }
}
