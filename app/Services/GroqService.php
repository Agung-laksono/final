<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.groq.com/openai/v1';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY'); // We keep the env name the same for compatibility
    }

    /**
     * Send a prompt to Groq and get a text response.
     */
    public function generateText(string $prompt, string $model = 'llama-3.3-70b-versatile'): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('API Key is missing.');
            return "Maaf, sistem AI belum dikonfigurasi (API Key hilang).";
        }

        $url = "{$this->baseUrl}/chat/completions";

        try {
            $response = Http::withoutVerifying()
                ->withToken($this->apiKey)
                ->post($url, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 800,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? null;
            }

            // Fallback Mock Response for Testing if Quota Limit Hit
            if ($response->status() === 429 || $response->status() === 404) {
                preg_match('/Total Pesanan: (\d+)/', $prompt, $totalMatches);
                $total = $totalMatches[1] ?? 0;
                return "*(Mode Simulasi)*\n\nHalo! Berdasarkan data *real-time* yang Anda kirimkan, saat ini pabrik kita memiliki total **{$total} pesanan aktif**. Saya telah menerima seluruh konteks Anda, namun ini adalah pesan simulasi karena limit kuota/model.";
            }

            Log::error('Groq API Error', ['response' => $response->body()]);
            return "Maaf, terjadi kesalahan saat menghubungi server AI: " . $response->status();

        } catch (\Exception $e) {
            Log::error('Groq Connection Error', ['exception' => $e->getMessage()]);
            return "Maaf, koneksi ke server AI terputus. (" . $e->getMessage() . ")";
        }
    }
    
    /**
     * Chat interface to maintain conversation history
     */
    public function chat(array $messages, array $tools = [], string $model = 'llama-3.3-70b-versatile')
    {
        if (empty($this->apiKey)) {
            return "Maaf, sistem AI belum dikonfigurasi (API Key hilang).";
        }

        $url = "{$this->baseUrl}/chat/completions";

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.2, // Lower temperature for more deterministic tool calling
            'max_tokens' => 1000,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withoutVerifying()
                ->withToken($this->apiKey)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $message = $data['choices'][0]['message'] ?? null;
                
                if (isset($message['tool_calls'])) {
                    return $message; // Return the whole message array to handle tool calls
                }
                
                return $message['content'] ?? null;
            }

            $errorData = $response->json();
            
            // Fallback for Groq tool parsing bug where it misses a space before the JSON arguments
            if (isset($errorData['error']['code']) && $errorData['error']['code'] === 'tool_use_failed') {
                $failedGen = $errorData['error']['failed_generation'] ?? '';
                if (preg_match('/<function=([a-zA-Z0-9_]+)(.*?)(?:>)?\s*<\/function>/s', $failedGen, $matches)) {
                    $funcName = trim($matches[1]);
                    $funcArgs = trim($matches[2]);
                    
                    // Reconstruct the standard tool_calls array
                    return [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_' . substr(md5(uniqid()), 0, 8),
                                'type' => 'function',
                                'function' => [
                                    'name' => $funcName,
                                    'arguments' => $funcArgs
                                ]
                            ]
                        ]
                    ];
                }
            }

            Log::error('Groq Chat Error', ['response' => $response->body()]);
            return "Maaf, terjadi kesalahan saat menghubungi server AI. (" . $response->status() . ")";

        } catch (\Exception $e) {
            Log::error('Groq Chat Connection Error', ['exception' => $e->getMessage()]);
            return "Maaf, koneksi ke server AI terputus.";
        }
    }
}
