<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Communication\Models\WaConversation;
use Modules\Communication\Services\FonnteService;
use App\Services\VectorSearchService;

class WhatsAppAiBotService
{
    /**
     * Process incoming WhatsApp prompt and reply with AI Assistant answer.
     */
    public function processAndReply(string $phoneNumber, string $promptText, WaConversation $conversation = null, ?string $requestedProvider = null): ?string
    {
        try {
            // 1. Identify User by Phone Number
            $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
            $last8 = strlen($cleanPhone) > 8 ? substr($cleanPhone, -8) : $cleanPhone;
            
            $user = \App\Models\User::where('phone', 'like', "%{$last8}%")->first();

            $userName = $user ? $user->name : 'Pengguna WhatsApp';
            $userEmail = $user ? $user->email : '-';
            $userRole = 'Pelanggan/Umum';
            $isSuperAdmin = false;
            $hasFinanceAccess = false;
            $hasSalesAccess = false;
            $hasInventoryAccess = false;
            $hasProductionAccess = false;

            if ($user) {
                if (method_exists($user, 'getRoleNames') && $user->getRoleNames()->count() > 0) {
                    $userRole = implode(', ', $user->getRoleNames()->toArray());
                } elseif (isset($user->role)) {
                    $userRole = (string) $user->role;
                }

                $userRoleLower = strtolower($userRole);

                if (str_contains($userRoleLower, 'super admin') || (method_exists($user, 'hasRole') && $user->hasRole('Super Admin'))) {
                    $isSuperAdmin = true;
                    $hasFinanceAccess = true;
                    $hasSalesAccess = true;
                    $hasInventoryAccess = true;
                    $hasProductionAccess = true;
                } else {
                    $hasFinanceAccess = ($user->can('finance.dashboard.view') || $user->can('finance.inbox.view'))
                        || str_contains($userRoleLower, 'finance') || str_contains($userRoleLower, 'keuangan') || str_contains($userRoleLower, 'direktur') || str_contains($userRoleLower, 'manager');

                    $hasSalesAccess = ($user->can('sales.order.view') || $user->can('sales.customer.view'))
                        || str_contains($userRoleLower, 'sales') || str_contains($userRoleLower, 'penjualan');

                    $hasInventoryAccess = ($user->can('inventory.dashboard.view') || $user->can('inventory.item.view'))
                        || str_contains($userRoleLower, 'gudang') || str_contains($userRoleLower, 'inventory');

                    $hasProductionAccess = ($user->can('production.order.view'))
                        || str_contains($userRoleLower, 'produksi') || str_contains($userRoleLower, 'production');
                }
            }

            // 2. Perform RAG Vector Search with Role Restrictions
            $contextText = "";
            try {
                $vectorService = app(VectorSearchService::class);
                $relevantData = $vectorService->search($promptText, 8);

                $filteredData = array_filter($relevantData, function($data) use ($hasFinanceAccess, $hasProductionAccess) {
                    $modelClass = $data['model_type'] ?? '';
                    $contentText = strtolower($data['content_text'] ?? '');
                    
                    if (!$hasFinanceAccess) {
                        if (str_contains($modelClass, 'Finance') || str_contains($contentText, 'saldo kas') || str_contains($contentText, 'rekening bca') || str_contains($contentText, 'laba rugi') || str_contains($contentText, 'jurnal transaksi')) {
                            return false;
                        }
                    }

                    if (!$hasProductionAccess && !$hasFinanceAccess) {
                        if (str_contains($modelClass, 'Production') || str_contains($contentText, 'resep bom') || str_contains($contentText, 'biaya produksi')) {
                            return false;
                        }
                    }

                    return true;
                });

                if (count($filteredData) > 0) {
                    $contextText = "\n\n[DOKUMEN & CONTEXT DATA INTERNAL ERP TERSEDIA SESUAI HAK AKSES PERAN SAAT INI]:\n";
                    $idx = 1;
                    foreach ($filteredData as $data) {
                        $contextText .= ($idx++) . ". " . $data['content_text'] . "\n";
                    }
                }
            } catch (\Exception $e) {
                // Ignore RAG search errors
            }

            // 3. Load Active Provider and System Instructions
            $assistantName = Setting::where('key', 'ai_assistant_name')->value('value') ?? 'ROMLAH Asisten';
            $customInstruction = Setting::where('key', 'ai_custom_instruction')->value('value') ?? '';
            
            $personaPrompt = !empty(trim($customInstruction)) 
                ? "\nPetunjuk Khusus Persona & Gaya Bahasa:\n" . trim($customInstruction) . "\n"
                : "";

            $securityGuardrail = "\n[RESTRIKSI KEAMANAN DATA WHATSAPP SANGAT KETAT]:\n";
            $securityGuardrail .= "- Pengguna WhatsApp saat ini ('{$userName}') memiliki Peran/Jabatan di sistem ERP: '{$userRole}'.\n";

            if (!$hasFinanceAccess) {
                $securityGuardrail .= "- 🛑 DILARANG KERAS memberikan data keuangan/saldo kas kepada nomor WhatsApp ini karena tidak memiliki hak akses Finance.\n";
                $securityGuardrail .= "  Jika ditanyakan saldo/keuangan, JAWAB SOPAN: 'Mohon maaf {$userName}, informasi data keuangan perusahaan hanya dapat diakses oleh Divisi Finance.'\n";
            }

            $systemInstruction = "Kamu adalah {$assistantName}, AI Pintar Resmi ERP yang sedang membalas obrolan pelanggan/staf via WHATSAPP.
Pengguna WhatsApp yang sedang mengobrol denganmu saat ini:
- Nama Lengkap: {$userName}
- Peran/Jabatan: {$userRole}
{$personaPrompt}{$securityGuardrail}
Tugas utamamu adalah membantu {$userName} menjawab pertanyaan seputar operasional bisnis, stok barang, penjualan, pembelian, produksi, keuangan, atau informasi produk berdasarkan DATA INTERNAL ERP di atas.

Gaya Penulisan WhatsApp yang WAJIB dipatuhi:
- Format jawaban dengan cetak tebal (*kata*) untuk penekanan khas WhatsApp.
- Berikan jawaban yang ringkas, jelas, padat, dan ramah.
- Gunakan DOUBLE ENTER untuk memisahkan poin/paragraf.";

            // 4. Generate AI Reply with Requested Provider Priority
            $aiProvidersJson = Setting::where('key', 'ai_providers')->value('value');
            $providers = $aiProvidersJson ? json_decode($aiProvidersJson, true) : [];
            
            $targetProviderName = $requestedProvider ?: (Setting::where('key', 'active_ai_provider')->value('value') ?? 'Anthropic');

            usort($providers, function($a, $b) use ($targetProviderName) {
                $aName = strtolower($a['name'] ?? '');
                $bName = strtolower($b['name'] ?? '');
                $target = strtolower($targetProviderName);

                if (str_contains($aName, $target) || str_contains($target, $aName)) return -1;
                if (str_contains($bName, $target) || str_contains($target, $bName)) return 1;
                return 0;
            });

            $replyText = null;
            foreach ($providers as $p) {
                $pKey = trim($p['key'] ?? '');
                $pName = trim($p['name'] ?? '');
                if (!empty($pKey)) {
                    $resText = $this->callAiApi($pName, $pKey, $systemInstruction, $promptText, $contextText);
                    if (!empty($resText) && !str_contains(strtolower($resText), 'gagal')) {
                        $replyText = $resText;
                        break;
                    }
                }
            }

            if (empty($replyText) || str_contains(strtolower($replyText), 'gagal')) {
                $replyText = "Mohon maaf, terjadi kendala koneksi ke server AI Assistant. Mohon pastikan API Key provider diisi pada Pengaturan Integrasi.";
            }

            // 5. Send Response via Fonnte API
            if (!empty($replyText)) {
                $fonnte = app(FonnteService::class);
                $res = $fonnte->sendMessage($phoneNumber, $replyText);

                // Save to WaMessage DB if conversation is present
                if ($conversation) {
                    $waMessage = $conversation->messages()->create([
                        'fonnte_id' => is_array($res['id'] ?? null) ? $res['id'][0] : ($res['id'] ?? null),
                        'direction' => 'out',
                        'message_type' => 'text',
                        'message' => $replyText,
                        'status' => 'sent',
                    ]);

                    broadcast(new \Modules\Communication\Events\NewWhatsAppMessage($waMessage))->toOthers();
                }

                return $replyText;
            }

        } catch (\Exception $e) {
            Log::error("WhatsAppAiBotService Error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Call AI API Provider (Gemini / OpenAI / Claude / Groq)
     */
    protected function callAiApi(string $providerName, string $apiKey, string $systemInstruction, string $promptText, string $contextText): string
    {
        $nameLower = strtolower($providerName);

        if (str_contains($nameLower, 'openai')) {
            $res = Http::withoutVerifying()->withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemInstruction],
                    ['role' => 'user', 'content' => $promptText . $contextText]
                ],
            ]);
            return $res->json('choices.0.message.content') ?? 'Gagal memproses OpenAI.';
        } elseif (str_contains($nameLower, 'anthropic') || str_contains($nameLower, 'claude')) {
            $candidateClaudeModels = ['claude-3-5-haiku-20241022', 'claude-3-5-sonnet-20241022', 'claude-3-haiku-20240307', 'claude-3-sonnet-20240229'];
            $res = null;
            foreach ($candidateClaudeModels as $modelName) {
                $res = Http::withoutVerifying()->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json'
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model' => $modelName,
                    'max_tokens' => 1000,
                    'system' => $systemInstruction,
                    'messages' => [['role' => 'user', 'content' => $promptText . $contextText]]
                ]);

                if ($res && $res->successful() && !empty($res->json('content.0.text'))) {
                    return $res->json('content.0.text');
                }
            }

            if ($res) {
                Log::error("WhatsApp Claude AI Error: " . json_encode($res->json()));
            }
            return 'Gagal memproses AI Claude.';
        } else {
            // Default Gemini API with Candidate Fallback & withoutVerifying
            $payload = [
                'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $promptText . $contextText]]]
                ]
            ];

            $candidateModels = ['gemini-3.6-flash', 'gemini-2.5-flash', 'gemini-1.5-flash', 'gemini-2.0-flash'];
            $res = null;

            foreach ($candidateModels as $modelName) {
                $res = Http::withoutVerifying()
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}", $payload);
                
                if ($res && $res->successful() && !empty($res->json('candidates.0.content.parts.0.text'))) {
                    return $res->json('candidates.0.content.parts.0.text');
                }
            }

            if ($res) {
                Log::error("WhatsApp Gemini AI Error: " . json_encode($res->json()));
            }

            return 'Gagal memproses AI Gemini.';
        }
    }
}
