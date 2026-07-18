<?php

namespace Modules\Communication\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    protected string $token;
    protected string $apiUrl = 'https://api.fonnte.com';

    public function __construct()
    {
        $this->token = config('services.fonnte.token') ?? env('FONNTE_TOKEN', '');
    }

    /**
     * Send a text message via Fonnte.
     *
     * @param string|array $target Target phone number(s) (can be comma separated string)
     * @param string $message The text message
     * @return array|null Response from Fonnte API or null on error
     */
    public function sendMessage(string|array $target, string $message): ?array
    {
        if (is_array($target)) {
            $target = implode(',', $target);
        }

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => $this->token,
            ])->post("{$this->apiUrl}/send", [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62', // Default to Indonesia
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Fonnte sendMessage API Error: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('Fonnte sendMessage Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send a media message via Fonnte.
     *
     * @param string|array $target Target phone number(s)
     * @param string $url URL of the media file
     * @param string $type Type of media (image, document, audio, video)
     * @param string $message Optional caption
     * @return array|null
     */
    public function sendMedia(string|array $target, string $url, string $type, string $message = ''): ?array
    {
        if (is_array($target)) {
            $target = implode(',', $target);
        }

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => $this->token,
            ])->post("{$this->apiUrl}/send", [
                'target' => $target,
                'message' => $message,
                'url' => $url,
                'type' => $type, // Must match Fonnte requirements
                'countryCode' => '62',
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Fonnte sendMedia API Error: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('Fonnte sendMedia Exception: ' . $e->getMessage());
            return null;
        }
    }
}
