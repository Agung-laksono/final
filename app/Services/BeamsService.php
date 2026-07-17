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
    public function sendToAll(string $title, string $body, array $data = [], string $url = '/'): bool
    {
        return $this->publish(['all-users'], $title, $body, $data, $url);
    }

    /**
     * Kirim notifikasi ke user spesifik berdasarkan ID
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = [], string $url = '/'): bool
    {
        return $this->publish(['user-' . $userId], $title, $body, $data, $url);
    }

    /**
     * Kirim notifikasi ke role tertentu (misal: 'role-manager')
     */
    public function sendToRole(string $role, string $title, string $body, array $data = [], string $url = '/'): bool
    {
        $interest = 'role-' . strtolower(str_replace(' ', '-', $role));
        return $this->publish([$interest], $title, $body, $data, $url);
    }

    /**
     * Internal: kirim ke daftar interests
     */
    protected function publish(array $interests, string $title, string $body, array $data, string $url): bool
    {
        $baseUrl = request()->getSchemeAndHttpHost();
        
        try {
            $this->beams->publishToInterests($interests, [
                'web' => [
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                        'icon'  => $baseUrl . '/apple-touch-icon.png',
                        'deep_link' => $baseUrl . $url,
                    ],
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
