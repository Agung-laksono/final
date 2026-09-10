<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use App\Services\VectorSearchService;
use App\Events\InternalMessageSent;

class ProcessAiChatResponse implements ShouldQueue
{
    use Queueable;

    public $message;
    public $timeout = 120; // Waktu ekstra untuk nunggu API AI
    public bool $useRag;
    public string $selectedProvider;

    public function __construct(ChatMessage $message, bool $useRag = true, string $selectedProvider = '')
    {
        $this->message = $message;
        $this->useRag = $useRag;
        $this->selectedProvider = $selectedProvider;
    }

    public function handle(): void
    {
        $conv = $this->message->conversation;
        $user = $this->message->sender;

        if (!$conv || $conv->type !== 'ai' || !$user) {
            return;
        }

        $userId = $user->id;
        $userName = $user->name;
        $userEmail = $user->email;
        $userRole = 'Karyawan';

        if (method_exists($user, 'getRoleNames') && $user->getRoleNames()->count() > 0) {
            $userRole = implode(', ', $user->getRoleNames()->toArray());
        } elseif (isset($user->role)) {
            $userRole = (string) $user->role;
        }

        $userRoleLower = strtolower($userRole);
        
        $isSuperAdmin = false;
        $hasFinanceAccess = false;
        $hasProductionAccess = false;

        if (str_contains($userRoleLower, 'super admin') || (method_exists($user, 'hasRole') && $user->hasRole('Super Admin'))) {
            $isSuperAdmin = true;
            $hasFinanceAccess = true;
            $hasProductionAccess = true;
        } else {
            $hasFinanceAccess = ($user->can('finance.dashboard.view') ?? false) || str_contains($userRoleLower, 'finance') || str_contains($userRoleLower, 'keuangan');
            $hasProductionAccess = ($user->can('production.order.view') ?? false) || str_contains($userRoleLower, 'produksi');
        }

        $promptText = $this->message->body;

        // RAG Context
        $contextText = "";
        if ($this->useRag) {
            try {
            $vectorService = app(VectorSearchService::class);
            $relevantData = $vectorService->search($promptText, 8);
            
            $filteredData = array_filter($relevantData, function($data) use ($hasFinanceAccess, $hasProductionAccess) {
                $modelClass = $data['model_type'] ?? '';
                if (!$hasFinanceAccess && str_contains($modelClass, 'Finance')) return false;
                if (!$hasProductionAccess && !$hasFinanceAccess && str_contains($modelClass, 'Production')) return false;
                return true;
            });

            if (count($filteredData) > 0) {
                $contextText = "\n\n[DOKUMEN & CONTEXT DATA INTERNAL ERP]:\n";
                $idx = 1;
                foreach ($filteredData as $data) {
                    $contextText .= ($idx++) . ". " . $data['content_text'] . "\n";
                }
            }
            } catch (\Exception $e) { }
        }

        // Setup AI Providers strictly from DB Settings (no env)
        $aiProvidersJson = Setting::where('key', 'ai_providers')->value('value');
        $providers = $aiProvidersJson ? json_decode($aiProvidersJson, true) : [];
        $activeProvider = null;
        
        $selectedProviderName = $this->selectedProvider ?: (Setting::where('key', 'ai_selected_provider')->value('value') ?? '');
        if ($selectedProviderName) {
            $activeProvider = collect($providers)->first(fn($p) => strtolower(trim($p['name'] ?? '')) === strtolower(trim($selectedProviderName)) && !empty(trim($p['key'] ?? '')));
        }
        if (!$activeProvider) {
            $activeProvider = collect($providers)->first(fn($p) => !empty(trim($p['key'] ?? '')));
        }

        if (!$activeProvider || empty(trim($activeProvider['key'] ?? ''))) {
            $this->saveAiResponse($conv, "⚠️ **API Key Belum Dikonfigurasi**\n\nSilakan masukkan API Key di menu Pengaturan > Integrasi (http://127.0.0.1:8000/settings/integrations).");
            return;
        }

        $apiKey = trim($activeProvider['key']);
        $providerName = trim($activeProvider['name'] ?? 'AI');
        $assistantName = Setting::where('key', 'ai_assistant_name')->value('value') ?? 'ROMLAH Asisten';
        $customInstruction = Setting::where('key', 'ai_custom_instruction')->value('value') ?? '';
        
        $personaPrompt = !empty(trim($customInstruction)) ? "\nPetunjuk Khusus:\n" . trim($customInstruction) . "\n" : "";

        $systemInstruction = "Kamu adalah {$assistantName}, AI Pintar Resmi dalam sistem ERP perusahaan.
Berbicara dengan: {$userName} ({$userRole})
{$personaPrompt}
Jawab secara singkat, rapi (gunakan poin/tabel), dan langsung ke inti pertanyaan.";

        // Format history
        $chatHistory = $conv->messages()->oldest()->take(15)->get();
        $messagesPayload = [['role' => 'system', 'content' => $systemInstruction]];
        
        foreach ($chatHistory as $msg) {
            $role = $msg->sender_id ? 'user' : 'assistant';
            $messagesPayload[] = ['role' => $role, 'content' => $msg->body];
        }

        if ($contextText !== "") {
            $lastIdx = count($messagesPayload) - 1;
            $messagesPayload[$lastIdx]['content'] .= $contextText . "\n\nJawab berdasarkan DATA INTERNAL di atas jika relevan.";
        }

        $replyText = "Maaf, terjadi kesalahan saat menghubungi server AI.";
        $nameLower = strtolower($providerName);

        try {
            if (str_contains($nameLower, 'openai')) {
                $res = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => $messagesPayload,
                ]);
                if ($res->successful()) {
                    $replyText = $res->json('choices.0.message.content');
                } else {
                    $err = $res->json('error.message') ?? $res->body();
                    $replyText = "⚠️ **OpenAI Error**: {$err}";
                }
            } elseif (str_contains($nameLower, 'anthropic') || str_contains($nameLower, 'claude')) {
                $res = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json'
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-5-haiku-20241022',
                    'max_tokens' => 1000,
                    'system' => $systemInstruction,
                    'messages' => array_slice($messagesPayload, 1)
                ]);
                if ($res->successful()) {
                    $replyText = $res->json('content.0.text');
                } else {
                    $err = $res->json('error.message') ?? $res->body();
                    $replyText = "⚠️ **Anthropic Error**: {$err}";
                }
            } elseif (str_contains($nameLower, 'groq')) {
                $res = Http::withToken($apiKey)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => $messagesPayload,
                ]);
                if ($res->successful()) {
                    $replyText = $res->json('choices.0.message.content');
                } else {
                    $err = $res->json('error.message') ?? $res->body();
                    $replyText = "⚠️ **Groq Error**: {$err}";
                }
            } else {
                // Gemini fallback models
                $geminiHistory = [];
                foreach ($chatHistory as $msg) {
                    $geminiHistory[] = [
                        'role' => $msg->sender_id ? 'user' : 'model',
                        'parts' => [['text' => $msg->body]]
                    ];
                }
                if ($contextText !== "") {
                    $lastIdx = count($geminiHistory) - 1;
                    $geminiHistory[$lastIdx]['parts'][0]['text'] .= $contextText . "\n\nJawab berdasarkan DATA INTERNAL di atas jika relevan.";
                }

                $geminiModels = ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-2.5-flash'];
                $res = null;
                foreach ($geminiModels as $gModel) {
                    $res = Http::post("https://generativelanguage.googleapis.com/v1beta/models/{$gModel}:generateContent?key={$apiKey}", [
                        'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                        'contents' => $geminiHistory,
                        'generationConfig' => ['temperature' => 0.7]
                    ]);
                    if ($res->successful()) {
                        $replyText = $res->json('candidates.0.content.parts.0.text');
                        break;
                    }
                }

                if (!$res || !$res->successful()) {
                    $err = ($res ? $res->json('error.message') : null) ?? 'Gagal menghubungi Gemini API';
                    $replyText = "⚠️ **Gemini Error**: {$err}";
                }
            }
        } catch (\Exception $e) {
            $replyText = "Terjadi kesalahan internal AI: " . $e->getMessage();
        }

        $this->saveAiResponse($conv, $replyText);
    }

    protected function saveAiResponse($conv, $text)
    {
        $aiMessage = $conv->messages()->create([
            'sender_id' => null, // AI Message
            'body' => $text,
            'type' => 'text'
        ]);

        $conv->update(['last_message_at' => now()]);

        broadcast(new InternalMessageSent($aiMessage));
    }
}
