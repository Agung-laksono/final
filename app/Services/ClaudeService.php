<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $keyInDb = null;
        $aiProvidersJson = \App\Models\Setting::where('key', 'ai_providers')->value('value');
        if ($aiProvidersJson) {
            $providers = json_decode($aiProvidersJson, true) ?? [];
            foreach ($providers as $p) {
                if (str_contains(strtolower($p['name'] ?? ''), 'claude') || str_contains(strtolower($p['name'] ?? ''), 'anthropic')) {
                    if (!empty(trim($p['key'] ?? ''))) {
                        $keyInDb = trim($p['key']);
                        break;
                    }
                }
            }
        }
        $this->apiKey = $keyInDb ?: '';
    }

    public function chat(array $messages, array $tools = [], string $systemPrompt = '')
    {
        if (empty($this->apiKey)) {
            return "Maaf, API Key Claude belum diisi. Silakan atur API Key pada menu Pengaturan > Integrasi.";
        }

        $url = "{$this->baseUrl}/messages";

        $payload = [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 2048,
            'messages' => $messages,
        ];

        if (!empty($systemPrompt)) {
            $payload['system'] = $systemPrompt;
        }

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(120)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // If stop_reason is tool_use, return the content array so the caller can execute the tool
                if (($data['stop_reason'] ?? '') === 'tool_use') {
                    return $data['content']; // This contains 'tool_use' block(s) and possibly 'text' blocks
                }
                
                // Return standard text
                $textResult = '';
                foreach ($data['content'] ?? [] as $block) {
                    if ($block['type'] === 'text') {
                        $textResult .= $block['text'];
                    }
                }
                return $textResult ?: "Maaf, balasan kosong.";
            }

            Log::error('Claude Chat Error', ['response' => $response->body()]);
            return "Maaf, terjadi kesalahan API Claude. (" . $response->status() . ")";

        } catch (\Exception $e) {
            Log::error('Claude Chat Connection Error', ['exception' => $e->getMessage()]);
            return "Maaf, koneksi ke server AI Claude terputus.";
        }
    }
}
