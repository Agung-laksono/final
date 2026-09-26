<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WorkspaceTaskUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $workspaceId;
    public string $action;
    public array $payload;

    /**
     * Create a new event instance.
     */
    public function __construct(int $workspaceId, string $action = 'updated', array $payload = [])
    {
        $this->workspaceId = $workspaceId;
        $this->action = $action;
        $this->payload = $payload;
    }

    /**
     * Cek apakah ada user lain yang sedang subscribe ke channel workspace ini.
     * Menggunakan Pusher HTTP API (channel info).
     * Di-cache 5 detik agar tidak spam API call saat banyak mutasi cepat.
     */
    public static function hasOtherSubscribers(int $workspaceId): bool
    {
        $cacheKey = "workspace_channel_occupied_{$workspaceId}";

        return Cache::remember($cacheKey, 5, function () use ($workspaceId) {
            try {
                /** @var \Illuminate\Broadcasting\BroadcastManager $broadcastManager */
                $broadcastManager = app(\Illuminate\Broadcasting\BroadcastManager::class);
                /** @var \Illuminate\Broadcasting\Broadcasters\PusherBroadcaster $broadcaster */
                $broadcaster = $broadcastManager->driver('pusher');
                $pusher = $broadcaster->getPusher();
                $info = $pusher->getChannelInfo('workspace.' . $workspaceId, ['info' => 'subscription_count']);
                $count = $info->subscription_count ?? 0;
                // >0 karena toOthers() sudah mengecualikan pengirim; jika ada 1+ subscriber = ada user lain
                return $count > 0;
            } catch (\Exception $e) {
                // Jika tidak bisa cek (Pusher mati, plan tidak support, dll), anggap ada subscriber
                Log::debug('WorkspaceTaskUpdated: could not check subscriber count: ' . $e->getMessage());
                return true;
            }
        });
    }

    /**
     * Dispatch dengan cerdas:
     * - Skip jika tidak ada user lain yang online di workspace (hemat kuota Pusher)
     * - Kirim payload data yang berubah agar client tidak perlu full DB reload
     * - Tetap aman jika Pusher mati
     */
    public static function safeDispatch(int $workspaceId, string $action = 'updated', array $payload = []): void
    {
        try {
            // Presence-Aware: hanya broadcast jika ada subscriber lain
            // Dinonaktifkan di local dev untuk kemudahan testing
            if (app()->isProduction() && !static::hasOtherSubscribers($workspaceId)) {
                Log::debug("WorkspaceTaskUpdated: skipped (no other subscribers) workspace={$workspaceId}");
                return;
            }

            broadcast(new static($workspaceId, $action, $payload))->toOthers();
        } catch (\Exception $e) {
            Log::warning('WorkspaceTaskUpdated Broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the channels the event should broadcast on.
     * Public channel — access control dilakukan di level PHP (abort 403 di mount).
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('workspace.' . $this->workspaceId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WorkspaceTaskUpdated';
    }

    /**
     * Rich payload: sertakan data yang berubah agar client bisa update state langsung
     * tanpa harus roundtrip ke server terlebih dahulu.
     * Client tetap bisa fallback ke loadProjects() jika payload tidak dikenali.
     */
    public function broadcastWith(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'action'       => $this->action,
            'timestamp'    => now()->timestamp,
            'data'         => $this->payload, // rich data untuk optimistic client update
        ];
    }
}
