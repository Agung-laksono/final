<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $key;
    public mixed $value;

    public function __construct(string $key, mixed $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * Safe broadcast — tidak crash jika server Reverb/Pusher mati.
     */
    public static function safeDispatch(string $key, mixed $value): void
    {
        try {
            // Broadcast ke SEMUA client termasuk pengirim (agar tab settings sendiri ikut update)
            broadcast(new static($key, $value));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('SettingUpdated broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast ke channel publik 'settings'
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('settings'),
        ];
    }
}
