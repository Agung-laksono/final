<?php

namespace App\Services;

use Pusher\PushNotifications\PushNotifications;
use Illuminate\Support\Facades\Log;

class BeamsService
{
    protected PushNotifications $beams;

    public function __construct()
    {
        $this->beams = new PushNotifications([
            'instanceId' => config('beams.instance_id'),
            'secretKey'  => config('beams.secret_key'),
        ]);
    }

    /**
     * Kirim notifikasi ke semua pengguna yang subscribe ke interest "all-users"
     */
    public function sendToAll(string $title, string $body, array $data = [], string $url = '/', ?string $image = null): bool
    {
        return $this->publish(['all-users'], $title, $body, $data, $url, $image);
    }

    /**
     * Kirim notifikasi ke user spesifik berdasarkan ID
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = [], string $url = '/', ?string $image = null): bool
    {
        return $this->publish(['user-' . $userId], $title, $body, $data, $url, $image);
    }

    /**
     * Kirim notifikasi ke role tertentu (misal: 'role-manager')
     */
    public function sendToRole(string $role, string $title, string $body, array $data = [], string $url = '/', ?string $image = null): bool
    {
        $interest = 'role-' . strtolower(str_replace(' ', '-', $role));
        return $this->publish([$interest], $title, $body, $data, $url, $image);
    }

    /**
     * Internal: kirim ke daftar interests
     */
    protected function publish(array $interests, string $title, string $body, array $data, string $url, ?string $image = null): bool
    {
        $baseUrl = request()->getSchemeAndHttpHost();
        $pwaIcon = \App\Models\Setting::where('key', 'pwa_icon')->value('value');
        $iconUrl = $pwaIcon ? $baseUrl . '/storage/' . $pwaIcon : $baseUrl . '/apple-touch-icon.png';
        
        $notificationData = [
            'title' => $title,
            'body'  => $body,
            'icon'  => $iconUrl,
            'deep_link' => $baseUrl . $url,
        ];

        if ($image) {
            $notificationData['image'] = str_starts_with($image, 'http') ? $image : $baseUrl . '/storage/' . ltrim($image, '/');
        }

        try {
            $this->beams->publishToInterests($interests, [
                'web' => [
                    'notification' => $notificationData,
                    'data' => array_merge($data, ['url' => $baseUrl . $url]),
                ],
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('[Beams] Gagal mengirim notifikasi: ' . $e->getMessage(), [
                'interests' => $interests,
                'title'     => $title,
            ]);
            return false;
        }
    }

    /**
     * Generate auth token untuk user yang login (authenticated interests)
     */
    public function generateToken(int $userId): array
    {
        return $this->beams->generateToken('user-' . $userId);
    }
}
