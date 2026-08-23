<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\VectorSearchService;
use App\Models\Setting;

new class extends Component
{
    public array $messages = [];
    public string $newMessage = '';
    public bool $useRag = true;
    public bool $isTyping = false;
    public string $selectedProvider = '';
    public array $configuredProviders = [];
    public string $assistantName = 'ROMLAH Asisten';
    public string $customInstruction = '';

    public function mount()
    {
        $userId = auth()->id() ?? 'guest';
        $history = Cache::get("ai_chat_history_{$userId}", []);
        $this->messages = array_map(function($msg) {
            unset($msg['is_new']);
            return $msg;
        }, $history);
        $this->assistantName = Setting::where('key', 'ai_assistant_name')->value('value') ?? 'ROMLAH Asisten';
        $this->customInstruction = Setting::where('key', 'ai_custom_instruction')->value('value') ?? '';
        $this->loadProviders();
    }

    public function loadProviders()
    {
        $this->configuredProviders = [];
        $aiProvidersJson = Setting::where('key', 'ai_providers')->value('value');
        
        if ($aiProvidersJson) {
            $providers = json_decode($aiProvidersJson, true) ?? [];
            foreach ($providers as $p) {
                if (!empty(trim($p['name'] ?? '')) && !empty(trim($p['key'] ?? ''))) {
                    $this->configuredProviders[] = [
                        'name' => trim($p['name']),
                        'key' => trim($p['key']),
                    ];
                }
            }
        }

        // Default selected provider to first available
        if (empty($this->selectedProvider) && !empty($this->configuredProviders)) {
            $this->selectedProvider = $this->configuredProviders[0]['name'];
        }
    }

    public function clearChat()
    {
        $userId = auth()->id() ?? 'guest';
        Cache::forget("ai_chat_history_{$userId}");
        $this->messages = [];
    }

    public function sendQuickPrompt(string $prompt)
    {
        if ($this->isTyping) return;
        $this->newMessage = $prompt;
        $this->sendMessage();
    }

    public function sendMessage()
    {
        $this->validate(['newMessage' => 'required|min:1']);
        
        $promptText = $this->newMessage;
        $this->newMessage = '';
        
        $this->messages[] = [
            'role' => 'user',
            'parts' => [['text' => $promptText]]
        ];

        $userId = auth()->id() ?? 'guest';
        Cache::put("ai_chat_history_{$userId}", $this->messages, now()->addHours(24));

        $this->isTyping = true;
        
        $this->dispatch('trigger-ai-response');
    }

    public function stopAiResponse()
    {
        $this->isTyping = false;
        $this->messages[] = [
            'role' => 'model',
            'parts' => [['text' => '🛑 *Proses pembuatan jawaban AI dihentikan oleh pengguna.*']]
        ];

        $userId = auth()->id() ?? 'guest';
        $cleanMessages = array_map(function($m) {
            $clean = $m;
            unset($clean['is_new']);
            return $clean;
        }, $this->messages);

        Cache::put("ai_chat_history_{$userId}", $cleanMessages, now()->addHours(24));
    }

    #[\Livewire\Attributes\On('trigger-ai-response')]
    public function generateAiResponse()
    {
        if (!$this->isTyping) {
            return;
        }

        $this->loadProviders();

        $lastMessage = end($this->messages);
        if (!$lastMessage || $lastMessage['role'] !== 'user') {
            $this->isTyping = false;
            return;
        }

        $promptText = $lastMessage['parts'][0]['text'];
        $userId = auth()->id() ?? 'guest';
        $user = auth()->user();
        $userName = $user ? $user->name : 'Pengguna ERP';
        $userEmail = $user ? $user->email : '-';
        $userRole = 'Tamu/Guest';
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
            
            if (str_contains($userRoleLower, 'super admin') || str_contains($userRoleLower, 'admin') || str_contains($userRoleLower, 'direktur') || str_contains($userRoleLower, 'owner') || str_contains($userRoleLower, 'manager')) {
                $isSuperAdmin = true;
                $hasFinanceAccess = true;
                $hasSalesAccess = true;
                $hasInventoryAccess = true;
                $hasProductionAccess = true;
            } else {
                $hasFinanceAccess = str_contains($userRoleLower, 'finance') || str_contains($userRoleLower, 'keuangan') || (method_exists($user, 'can') && $user->can('view-finance'));
                $hasSalesAccess = str_contains($userRoleLower, 'sales') || str_contains($userRoleLower, 'penjualan') || (method_exists($user, 'can') && $user->can('view-sales'));
                $hasInventoryAccess = str_contains($userRoleLower, 'gudang') || str_contains($userRoleLower, 'inventory') || (method_exists($user, 'can') && $user->can('view-inventory'));
                $hasProductionAccess = str_contains($userRoleLower, 'produksi') || str_contains($userRoleLower, 'production') || (method_exists($user, 'can') && $user->can('view-production'));
            }
        }

        // Prepare RAG Context with Role Security Filtering
        $contextText = "";
        if ($this->useRag) {
            try {
                $vectorService = app(VectorSearchService::class);
                $relevantData = $vectorService->search($promptText, 10);
                
                // Filter out documents user is not authorized to see
                $filteredData = array_filter($relevantData, function($data) use ($hasFinanceAccess, $hasProductionAccess) {
                    $modelClass = $data['model_type'] ?? '';
                    $contentText = strtolower($data['content_text'] ?? '');
                    
                    // Filter Finance Data for non-finance users
                    if (!$hasFinanceAccess) {
                        if (str_contains($modelClass, 'Finance') || str_contains($contentText, 'saldo kas') || str_contains($contentText, 'rekening bca') || str_contains($contentText, 'laba rugi') || str_contains($contentText, 'jurnal transaksi')) {
                            return false;
                        }
                    }

                    // Filter Production Data for non-production non-admin users
                    if (!$hasProductionAccess && !$hasFinanceAccess) {
                        if (str_contains($modelClass, 'Production') || str_contains($contentText, 'resep bom') || str_contains($contentText, 'biaya produksi')) {
                            return false;
                        }
                    }

                    return true;
                });

                if (count($filteredData) > 0) {
                    $contextText = "\n\n[DOKUMEN & CONTEXT DATA INTERNAL ERP TERSEDIA SESUAI HAK AKSES PERAN PENGGUNA SAAT INI]:\n";
                    $idx = 1;
                    foreach ($filteredData as $data) {
                        $contextText .= ($idx++) . ". " . $data['content_text'] . "\n";
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        // Find active selected provider config
        $activeProvider = collect($this->configuredProviders)
            ->first(fn($p) => strtolower($p['name']) === strtolower($this->selectedProvider));

        if (!$activeProvider && !empty($this->configuredProviders)) {
            $activeProvider = $this->configuredProviders[0];
            $this->selectedProvider = $activeProvider['name'];
        }

        if (!$activeProvider || empty($activeProvider['key'])) {
            $this->messages[] = [
                'role' => 'model',
                'parts' => [['text' => "⚠️ **API Key Belum Dikonfigurasi**\n\nSilakan masukkan API Key untuk provider **{$this->selectedProvider}** pada menu **Pengaturan > Integrasi**."]]
            ];
            $this->isTyping = false;
            return;
        }

        $apiKey = $activeProvider['key'];
        $providerName = $activeProvider['name'];

        $assistantName = $this->assistantName ?: 'ROMLAH Asisten';
        $personaPrompt = !empty(trim($this->customInstruction)) 
            ? "\nPetunjuk Khusus Persona & Gaya Bahasa:\n" . trim($this->customInstruction) . "\n"
            : "";

        $securityGuardrail = "\n[RESTRIKSI HAK AKSES PERAN & KEAMANAN DATA SANGAT KETAT]:\n";
        $securityGuardrail .= "- Pengguna saat ini ('{$userName}') memiliki Peran/Jabatan: '{$userRole}'.\n";

        if (!$hasFinanceAccess) {
            $securityGuardrail .= "- 🛑 DILARANG KERAS memberikan informasi apapun terkait KEUANGAN PERUSAHAAN (Saldo Kas, Mutasi Rekening, Omset/Laba Rugi, Piutang Keuangan, atau Laporan Kas Bank). Pengguna ini TIDAK MEMILIKI HAK AKSES FINANCE.\n";
            $securityGuardrail .= "  Jika pengguna menanyakan data keuangan/saldo kas/laba rugi, JAWAB DENGAN SOPAN: 'Mohon maaf {$userName}, informasi data keuangan & saldo kas perusahaan hanya dapat diakses oleh Divisi Finance dan Manajemen.'\n";
        }
        if (!$hasProductionAccess && !$isSuperAdmin) {
            $securityGuardrail .= "- 🛑 DILARANG KERAS membeberkan detail resep rahasia/BOM produksi atau HPP produksi internal kecuali hanya status umum jadwal produksi.\n";
        }

        $systemInstruction = "Kamu adalah {$assistantName}, AI Pintar Resmi terintegrasi dalam sistem ERP perusahaan.
Pengguna yang sedang berinteraksi dan berbicara denganmu saat ini adalah:
- Nama Lengkap: {$userName}
- Email: {$userEmail}
- Peran/Jabatan: {$userRole}
{$personaPrompt}{$securityGuardrail}
Tugas utamamu adalah membantu {$userName} menjawab pertanyaan seputar operasional bisnis, stok barang, penjualan (Sales Order), pembelian (Purchase Order), produksi, keuangan, pelanggan, dan vendor berdasarkan DATA INTERNAL ERP yang diizinkan di atas secara tepat, akurat, profesional, dan ramah.

Gaya Penulisan yang WAJIB dipatuhi:
- TIDAK PERLU selalu mengulang sapaan 'Halo [Nama]' atau salam pembuka di setiap jawaban obrolan. Langsung jawab inti pertanyaan secara natural, efektif, dan profesional. (Hanya sapa jika diawal percakapan baru).
- Utamakan menggunakan informasi dari [DOKUMEN & CONTEXT DATA INTERNAL ERP TERSEDIA SESUAI HAK AKSES PERAN PENGGUNA SAAT INI].
- Sajikan informasi secara sangat terstruktur. Gunakan format tabel markdown atau poin-poin (bullet list) untuk penjelasan yang memuat daftar item/transaksi.
- Format seluruh angka nominal uang menjadi Rupiah yang rapi (contoh: Rp 15.000.000).
- Gunakan DOUBLE ENTER (baris kosong) untuk memisahkan setiap paragraf atau poin agar tampilan bersih dan nyaman dibaca.
- Jawab secara singkat, jelas, padat, dan langsung menjawab inti pertanyaan pengguna.";

        try {
            $replyText = null;
            $nameLower = strtolower($providerName);

            if (str_contains($nameLower, 'openai')) {
                // OpenAI API Call
                $messagesPayload = [['role' => 'system', 'content' => $systemInstruction]];
                foreach ($this->messages as $msg) {
                    $role = $msg['role'] === 'user' ? 'user' : 'assistant';
                    $text = $msg['parts'][0]['text'];
                    $messagesPayload[] = ['role' => $role, 'content' => $text];
                }
                if ($contextText !== "") {
                    $lastIdx = count($messagesPayload) - 1;
                    $messagesPayload[$lastIdx]['content'] .= $contextText . "\n\nJawab berdasarkan DATA INTERNAL di atas jika relevan.";
                }

                $res = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => $messagesPayload,
                ]);

                if ($res->successful()) {
                    $replyText = $res->json('choices.0.message.content');
                } else {
                    $err = $res->json('error.message') ?? $res->status();
                    throw new \Exception("OpenAI Error: {$err}");
                }
            } elseif (str_contains($nameLower, 'anthropic') || str_contains($nameLower, 'claude')) {
                // Anthropic Claude API Call
                $messagesPayload = [];
                foreach ($this->messages as $msg) {
                    $role = $msg['role'] === 'user' ? 'user' : 'assistant';
                    $text = $msg['parts'][0]['text'];
                    $messagesPayload[] = ['role' => $role, 'content' => $text];
                }
                if ($contextText !== "") {
                    $lastIdx = count($messagesPayload) - 1;
                    $messagesPayload[$lastIdx]['content'] .= $contextText . "\n\nJawab berdasarkan DATA INTERNAL di atas jika relevan.";
                }

                $candidateClaudeModels = ['claude-haiku-4-5-20251001', 'claude-sonnet-4-5-20250929', 'claude-sonnet-4-6', 'claude-sonnet-5', 'claude-3-5-haiku-20241022'];
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
                        'messages' => $messagesPayload
                    ]);

                    if ($res->successful()) break;
                }

                if ($res && $res->successful()) {
                    $replyText = $res->json('content.0.text');
                } else {
                    $err = ($res ? $res->json('error.message') : null) ?? 'Gagal menghubungi server Claude';
                    throw new \Exception("Claude Error: {$err}");
                }
            } elseif (str_contains($nameLower, 'groq')) {
                // Groq API Call
                $messagesPayload = [['role' => 'system', 'content' => $systemInstruction]];
                foreach ($this->messages as $msg) {
                    $role = $msg['role'] === 'user' ? 'user' : 'assistant';
                    $text = $msg['parts'][0]['text'];
                    $messagesPayload[] = ['role' => $role, 'content' => $text];
                }
                if ($contextText !== "") {
                    $lastIdx = count($messagesPayload) - 1;
                    $messagesPayload[$lastIdx]['content'] .= $contextText . "\n\nJawab berdasarkan DATA INTERNAL di atas jika relevan.";
                }

                $res = Http::withoutVerifying()->withToken($apiKey)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => $messagesPayload,
                ]);

                if ($res->successful()) {
                    $replyText = $res->json('choices.0.message.content');
                } else {
                    $err = $res->json('error.message') ?? $res->status();
                    throw new \Exception("Groq Error: {$err}");
                }
            } else {
                // Default: Google Gemini API Call
                $historyForGemini = [];
                foreach ($this->messages as $msg) {
                    $historyForGemini[] = [
                        'role' => $msg['role'],
                        'parts' => $msg['parts']
                    ];
                }
                if ($contextText !== "") {
                    $lastIndex = count($historyForGemini) - 1;
                    $historyForGemini[$lastIndex]['parts'][0]['text'] .= $contextText . "\n\nJawab pertanyaan pengguna berdasarkan DATA INTERNAL di atas jika relevan.";
                }

                $payload = [
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]]
                    ],
                    'contents' => $historyForGemini
                ];

                $candidateModels = ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-2.5-flash', 'gemini-3.6-flash'];
                $res = null;
                foreach ($candidateModels as $modelName) {
                    $res = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}", $payload);
                    if ($res->successful()) break;
                }

                if ($res && $res->successful()) {
                    $replyText = $res->json('candidates.0.content.parts.0.text');
                } else {
                    $err = ($res ? $res->json('error.message') : null) ?? 'Gagal menghubungi server Gemini AI';
                    throw new \Exception("Gemini Error: {$err}");
                }
            }

            if ($replyText && $this->isTyping) {
                $this->messages[] = [
                    'role' => 'model',
                    'parts' => [['text' => $replyText]],
                    'is_new' => true 
                ];

                // Simpan ke Cache TANPA flag is_new agar tab lain tidak ikut me-replay animasi penulisan
                $cleanMessages = array_map(function($m) {
                    $clean = $m;
                    unset($clean['is_new']);
                    return $clean;
                }, $this->messages);

                Cache::put("ai_chat_history_{$userId}", $cleanMessages, now()->addHours(24));
            } elseif (!$this->isTyping) {
                // Cancelled during HTTP execution
                return;
            } else {
                throw new \Exception("Tidak mendapat respons dari AI.");
            }

        } catch (\Exception $e) {
            if ($this->isTyping) {
                $this->messages[] = [
                    'role' => 'model',
                    'parts' => [['text' => "⚠️ **Kendala Koneksi AI ({$providerName})**\n\n*{$e->getMessage()}*.\n\n💡 **Solusi**: Silakan periksa kembali API Key **{$providerName}** pada menu **Pengaturan > Integrasi**."]]
                ];
            }
        }

        $this->isTyping = false;
    }

    public function markAsOld()
    {
        foreach ($this->messages as &$msg) {
            if (isset($msg['is_new'])) {
                unset($msg['is_new']);
            }
        }
        $userId = auth()->id() ?? 'guest';
        Cache::put("ai_chat_history_{$userId}", $this->messages, now()->addHours(24));
    }
};
?>

<div
    x-data="{
        open: localStorage.getItem('ai_chat_open') === 'true',
        minimized: false,
        dragging: false,
        speechListening: false,
        recognition: null,
        startX: 0,
        posX: localStorage.getItem('ai_chat_pos') ? parseInt(localStorage.getItem('ai_chat_pos')) : (window.innerWidth - 340 - 16),
        init() {
            if (this.posX < 0) this.posX = 8;
            
            // Sync saved RAG state to Livewire component
            let savedRag = localStorage.getItem('ai_chat_rag');
            if (savedRag !== null) {
                $wire.useRag = (savedRag === 'true');
            }

            // Persist states automatically on change
            this.$watch('open', val => localStorage.setItem('ai_chat_open', val));
            this.$watch('posX', val => localStorage.setItem('ai_chat_pos', val));
            this.$watch('$wire.useRag', val => localStorage.setItem('ai_chat_rag', val));
        },
        startDrag(e) {
            if (e.target.closest('button') || e.target.closest('input') || e.target.closest('textarea') || e.target.closest('select')) return;
            this.dragging = true;
            this.startX = (e.touches ? e.touches[0].clientX : e.clientX) - this.posX;
            window.addEventListener('mousemove', this.onDrag.bind(this));
            window.addEventListener('touchmove', this.onDrag.bind(this), {passive: false});
            window.addEventListener('mouseup', this.stopDrag.bind(this));
            window.addEventListener('touchend', this.stopDrag.bind(this));
        },
        onDrag(e) {
            if (!this.dragging) return;
            e.preventDefault();
            let x = (e.touches ? e.touches[0].clientX : e.clientX) - this.startX;
            this.posX = Math.max(0, Math.min(x, window.innerWidth - 340));
        },
        stopDrag() {
            this.dragging = false;
            window.removeEventListener('mousemove', this.onDrag.bind(this));
            window.removeEventListener('touchmove', this.onDrag.bind(this));
            window.removeEventListener('mouseup', this.stopDrag.bind(this));
            window.removeEventListener('touchend', this.stopDrag.bind(this));
        },
        scrollBottom() {
            this.$nextTick(() => {
                let c = document.getElementById('ai-chat-container');
                if(c) c.scrollTop = c.scrollHeight;
            });
        },
        copyAnswerText(el) {
            if(!el) return;
            let text = el.innerText || el.textContent;
            navigator.clipboard.writeText(text).then(() => {
                if (typeof Flux !== 'undefined' && Flux.toast) {
                    Flux.toast('Jawaban AI berhasil disalin!', { variant: 'success' });
                }
            });
        },
        toggleVoiceInput() {
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                alert('Browser Anda belum mendukung fitur Voice Input. Gunakan Chrome atau Edge terbaru.');
                return;
            }
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (this.speechListening) {
                if (this.recognition) this.recognition.stop();
                this.speechListening = false;
                return;
            }
            this.recognition = new SpeechRecognition();
            this.recognition.lang = 'id-ID';
            this.recognition.interimResults = false;
            this.recognition.onstart = () => { this.speechListening = true; };
            this.recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                $wire.newMessage = ($wire.newMessage ? $wire.newMessage + ' ' : '') + transcript;
                this.speechListening = false;
            };
            this.recognition.onerror = () => { this.speechListening = false; };
            this.recognition.onend = () => { this.speechListening = false; };
            this.recognition.start();
        },
        exportChatLog() {
            if ($wire.messages.length === 0) return;
            let text = '=== DIALOG OBROLAN ROMLAH ASISTEN ERP ===\n';
            text += 'Tanggal Export: ' + new Date().toLocaleString('id-ID') + '\n\n';
            $wire.messages.forEach((m, idx) => {
                let role = m.role === 'user' ? 'PENGGUNA' : 'ROMLAH AI';
                let content = m.parts && m.parts[0] ? m.parts[0].text : '';
                text += `[${idx+1}] ${role}:\n${content}\n\n-----------------------------------\n\n`;
            });
            let blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            let a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `analisis-romlah-ai-${new Date().toISOString().slice(0,10)}.txt`;
            a.click();
        }
    }"
    style="position: fixed; inset: 0; pointer-events: none; z-index: 9999;"
>
    <style>
        #ai-chat-container table {
            width: 100%;
            border-collapse: collapse;
            margin: 0.5rem 0;
            font-size: 11px;
            overflow-x: auto;
            display: block;
        }
        #ai-chat-container th {
            background-color: rgba(99, 102, 241, 0.12);
            color: #4f46e5;
            font-weight: 700;
            padding: 5px 8px;
            border: 1px solid rgba(228, 228, 231, 0.8);
            text-align: left;
        }
        .dark #ai-chat-container th {
            background-color: rgba(99, 102, 241, 0.25);
            color: #818cf8;
            border-color: rgba(63, 63, 70, 0.8);
        }
        #ai-chat-container td {
            padding: 4px 8px;
            border: 1px solid rgba(228, 228, 231, 0.8);
        }
        .dark #ai-chat-container td {
            border-color: rgba(63, 63, 70, 0.8);
        }
        #ai-chat-container tr:nth-child(even) {
            background-color: rgba(244, 244, 245, 0.6);
        }
        .dark #ai-chat-container tr:nth-child(even) {
            background-color: rgba(39, 39, 42, 0.6);
        }
    </style>

    {{-- TRIGGER BUTTON --}}
    <button
        type="button"
        x-show="!open"
        x-transition:enter="transition ease-out duration-200 delay-150"
        x-transition:enter-start="opacity-0 scale-50"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-50"
        @click="open = !open; minimized = false; if(open) { scrollBottom(); $dispatch('ai-chat-opened'); }"
        class="absolute right-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] md:right-6 md:bottom-6 w-10 h-10 lg:w-12 lg:h-12 bg-indigo-500/10 hover:bg-indigo-500/20 dark:bg-indigo-500/20 dark:hover:bg-indigo-500/30 backdrop-blur-md border border-indigo-500/30 rounded-full shadow-lg flex items-center justify-center text-indigo-600 dark:text-indigo-400 transition-colors pointer-events-auto"
        title="Tanya AI"
    >
        <flux:icon.sparkles class="w-4 h-4 lg:w-5 lg:h-5" />
    </button>

    {{-- MESSENGER POPUP --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95 translate-y-2"
        :style="`left: ${posX}px; position: absolute;`"
        class="bottom-[calc(5.5rem+env(safe-area-inset-bottom))] md:bottom-[85px] w-[350px] max-w-[calc(100vw-16px)] flex flex-col rounded-2xl shadow-[0_8px_40px_rgba(0,0,0,0.18)] overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 pointer-events-auto"
    >
        {{-- MESSENGER HEADER --}}
        <div
            @mousedown="startDrag($event)"
            @touchstart.passive="startDrag($event)"
            class="flex items-center gap-2 px-3 py-2.5 bg-white dark:bg-zinc-900 border-b border-zinc-100 dark:border-zinc-800 cursor-grab active:cursor-grabbing select-none shrink-0"
        >
            {{-- Avatar dengan status online --}}
            <div class="relative shrink-0">
                <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white shadow-sm">
                    <flux:icon.sparkles class="w-4 h-4" />
                </div>
                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-white dark:border-zinc-900 rounded-full"></span>
            </div>

            {{-- Nama & Provider Selector --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1">
                    <span class="font-bold text-[13px] text-zinc-900 dark:text-white truncate">{{ $assistantName }}</span>
                </div>
                @if(count($configuredProviders) > 0)
                    <div class="relative inline-block mt-0.5">
                        <select 
                            wire:model.live="selectedProvider" 
                            class="text-[10px] font-medium bg-zinc-100 dark:bg-zinc-800 text-indigo-600 dark:text-indigo-400 border border-zinc-200 dark:border-zinc-700 rounded px-1.5 py-0.5 focus:outline-none focus:ring-0 cursor-pointer"
                        >
                            @foreach($configuredProviders as $p)
                                <option value="{{ $p['name'] }}">{{ $p['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <a href="{{ route('settings.integrations') }}" class="text-[10px] text-zinc-400 hover:text-indigo-500 font-medium">Set API Key</a>
                @endif
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center gap-0.5 shrink-0">
                @if(count($messages) > 0)
                    <button @click="exportChatLog()" title="Unduh Catatan Obrolan" class="w-7 h-7 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 transition-colors">
                        <flux:icon.arrow-down-tray class="w-3.5 h-3.5" />
                    </button>
                @endif
                <button wire:click="clearChat" title="Hapus Obrolan" class="w-7 h-7 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 transition-colors">
                    <flux:icon.trash class="w-3.5 h-3.5" />
                </button>
                <button @click="minimized = !minimized" title="Perkecil" class="w-7 h-7 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 transition-colors">
                    <flux:icon.minus class="w-3.5 h-3.5" />
                </button>
                <button @click="open = false" title="Tutup" class="w-7 h-7 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 transition-colors">
                    <flux:icon.x-mark class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>

        {{-- CHAT BODY (collapsible) --}}
        <div x-show="!minimized" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

            {{-- MESSAGES --}}
            <div
                id="ai-chat-container"
                class="overflow-y-auto px-3 py-3 space-y-2.5 bg-white dark:bg-zinc-900"
                style="height: 320px;"
            >
                @if(count($messages) === 0)
                    <div class="h-full flex flex-col items-center justify-center text-center pb-2 px-2">
                        <div class="w-12 h-12 rounded-full bg-indigo-600 flex items-center justify-center text-white shadow-md mb-2">
                            <flux:icon.sparkles class="w-6 h-6" />
                        </div>
                        <h4 class="font-bold text-xs text-zinc-800 dark:text-zinc-200">ROMLAH AI Asisten ERP</h4>
                        <p class="text-[11px] text-indigo-500 font-semibold mt-0.5">{{ $selectedProvider ?: 'Multi-AI System' }}</p>
                        
                        <!-- Quick Suggestion Chips (Role-Aware) -->
                        @php
                            $u = auth()->user();
                            $uRoleStr = strtolower($u ? (method_exists($u, 'getRoleNames') ? implode(',', $u->getRoleNames()->toArray()) : (string)($u->role ?? '')) : '');
                            $canSeeFinance = str_contains($uRoleStr, 'super admin') || str_contains($uRoleStr, 'admin') || str_contains($uRoleStr, 'finance') || str_contains($uRoleStr, 'keuangan') || str_contains($uRoleStr, 'direktur') || str_contains($uRoleStr, 'manager');
                        @endphp
                        <div class="mt-3 w-full space-y-1.5 text-left">
                            <p class="text-[10px] text-zinc-400 font-bold uppercase tracking-wider px-1">Pertanyaan Cepat:</p>
                            <div class="grid grid-cols-1 gap-1">
                                @if($canSeeFinance)
                                    <button wire:click="sendQuickPrompt('Berapa total saldo kas & bank hari ini?')" class="w-full text-left px-2.5 py-1.5 rounded-xl bg-zinc-100 hover:bg-indigo-50 dark:bg-zinc-800 dark:hover:bg-zinc-700/80 text-zinc-700 dark:text-zinc-300 text-[11px] font-medium border border-zinc-200/80 dark:border-zinc-700/80 transition-all flex items-center gap-1.5 group">
                                        <span class="text-indigo-500">💳</span>
                                        <span class="truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Total Saldo Kas Hari Ini</span>
                                    </button>
                                @endif
                                <button wire:click="sendQuickPrompt('Cek stok barang yang paling sedikit')" class="w-full text-left px-2.5 py-1.5 rounded-xl bg-zinc-100 hover:bg-indigo-50 dark:bg-zinc-800 dark:hover:bg-zinc-700/80 text-zinc-700 dark:text-zinc-300 text-[11px] font-medium border border-zinc-200/80 dark:border-zinc-700/80 transition-all flex items-center gap-1.5 group">
                                    <span class="text-amber-500">📦</span>
                                    <span class="truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Cek Stok Paling Menipis</span>
                                </button>
                                <button wire:click="sendQuickPrompt('Cek piutang penjualan (AR) yang belum lunas')" class="w-full text-left px-2.5 py-1.5 rounded-xl bg-zinc-100 hover:bg-indigo-50 dark:bg-zinc-800 dark:hover:bg-zinc-700/80 text-zinc-700 dark:text-zinc-300 text-[11px] font-medium border border-zinc-200/80 dark:border-zinc-700/80 transition-all flex items-center gap-1.5 group">
                                    <span class="text-blue-500">📥</span>
                                    <span class="truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Cek Piutang SO Belum Lunas</span>
                                </button>
                                <button wire:click="sendQuickPrompt('Tampilkan ringkasan Sales Order bulan ini')" class="w-full text-left px-2.5 py-1.5 rounded-xl bg-zinc-100 hover:bg-indigo-50 dark:bg-zinc-800 dark:hover:bg-zinc-700/80 text-zinc-700 dark:text-zinc-300 text-[11px] font-medium border border-zinc-200/80 dark:border-zinc-700/80 transition-all flex items-center gap-1.5 group">
                                    <span class="text-emerald-500">📑</span>
                                    <span class="truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Ringkasan Sales Order</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Group messages & show timestamps --}}
                @foreach($messages as $index => $msg)
                    @php $isUser = $msg['role'] === 'user'; @endphp
                    <div wire:key="msg-{{ $index }}" class="flex {{ $isUser ? 'justify-end' : 'justify-start items-end gap-1.5' }}">

                        {{-- AI avatar (only for AI messages) --}}
                        @if(!$isUser)
                            <div class="w-6 h-6 rounded-full bg-indigo-600 flex items-center justify-center text-white shrink-0 mb-0.5 shadow-sm">
                                <flux:icon.sparkles class="w-3 h-3" />
                            </div>
                        @endif

                        {{-- Bubble --}}
                        <div class="relative group max-w-[84%] px-3 py-2 text-[12px] leading-relaxed rounded-2xl
                            {{ $isUser
                                ? 'bg-indigo-600 text-white rounded-br-md'
                                : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 rounded-bl-md prose prose-sm dark:prose-invert max-w-none prose-p:mb-2 prose-p:leading-relaxed prose-ul:my-1.5 prose-li:my-0.5'
                            }}">
                            @if($isUser)
                                {{ $msg['parts'][0]['text'] }}
                            @else
                                @if(isset($msg['is_new']) && $msg['is_new'])
                                    <div x-data="{
                                        done: false,
                                        fullHtml: '',
                                        visibleHtml: '',
                                        init() {
                                            if (this.done) return;
                                            this.fullHtml = this.$refs.hiddenText.innerHTML;
                                            let i = 0, inTag = false, inEntity = false;
                                            let iv = setInterval(() => {
                                                if (i >= this.fullHtml.length) {
                                                    clearInterval(iv);
                                                    this.done = true;
                                                    this.$wire.call('markAsOld');
                                                    let c = document.getElementById('ai-chat-container');
                                                    if(c) c.scrollTop = c.scrollHeight;
                                                    return;
                                                }
                                                
                                                if (this.fullHtml[i] === '<') inTag = true;
                                                if (this.fullHtml[i] === '&') inEntity = true;
                                                
                                                while (inTag && i < this.fullHtml.length) {
                                                    this.visibleHtml += this.fullHtml[i];
                                                    if (this.fullHtml[i] === '>') inTag = false;
                                                    i++;
                                                }
                                                
                                                while (inEntity && i < this.fullHtml.length) {
                                                    this.visibleHtml += this.fullHtml[i];
                                                    if (this.fullHtml[i] === ';') inEntity = false;
                                                    i++;
                                                }
                                                
                                                if (i < this.fullHtml.length && !inTag && !inEntity) {
                                                    this.visibleHtml += this.fullHtml[i++];
                                                }

                                                if (i % 8 === 0) {
                                                    let c = document.getElementById('ai-chat-container');
                                                    if(c) c.scrollTop = c.scrollHeight;
                                                }
                                            }, 25);
                                        }
                                    }">
                                        <div style="display:none" x-ref="hiddenText">{!! Str::markdown($msg['parts'][0]['text']) !!}</div>
                                        <div x-ref="answerBody" x-html="visibleHtml"></div>
                                        
                                        <!-- Copy button for typing output -->
                                        <button 
                                            @click="copyAnswerText($refs.answerBody)" 
                                            class="mt-1.5 inline-flex items-center gap-1 text-[10px] text-zinc-400 hover:text-indigo-500 transition-colors"
                                            title="Salin Jawaban Ini"
                                        >
                                            <flux:icon.clipboard class="w-3 h-3" />
                                            <span>Salin Jawaban</span>
                                        </button>
                                    </div>
                                @else
                                    <div x-ref="answerStaticBody">{!! Str::markdown($msg['parts'][0]['text']) !!}</div>
                                    
                                    <!-- Copy button for static output -->
                                    <div class="mt-1.5 pt-1 border-t border-zinc-200/50 dark:border-zinc-700/50 flex justify-end">
                                        <button 
                                            @click="copyAnswerText($refs.answerStaticBody)" 
                                            class="inline-flex items-center gap-1 text-[10px] font-medium text-zinc-400 hover:text-indigo-500 transition-colors"
                                            title="Salin Jawaban Ini"
                                        >
                                            <flux:icon.clipboard class="w-3 h-3" />
                                            <span>Salin</span>
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- Typing indicator --}}
                @if($isTyping)
                    <div wire:key="typing" class="flex justify-start items-end gap-1.5">
                        <div class="w-6 h-6 rounded-full bg-indigo-600 flex items-center justify-center text-white shrink-0 mb-0.5 shadow-sm">
                            <flux:icon.sparkles class="w-3 h-3" />
                        </div>
                        <div class="bg-zinc-100 dark:bg-zinc-800 rounded-2xl rounded-bl-md px-3.5 py-2.5 flex gap-1 items-center">
                            <div class="w-1.5 h-1.5 bg-zinc-400 rounded-full animate-bounce"></div>
                            <div class="w-1.5 h-1.5 bg-zinc-400 rounded-full animate-bounce" style="animation-delay:.15s"></div>
                            <div class="w-1.5 h-1.5 bg-zinc-400 rounded-full animate-bounce" style="animation-delay:.3s"></div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- INPUT BAR --}}
            <div class="px-2 py-2 border-t border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shrink-0">
                {{-- RAG Toggle & Quick Actions --}}
                <div class="flex items-center justify-between px-1 mb-1.5">
                    <div class="flex items-center gap-1 text-zinc-400">
                        <flux:icon.cpu-chip class="w-3 h-3" />
                        <span class="text-[10px] font-semibold">Data Internal (RAG)</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer scale-[0.75] origin-right">
                        <input type="checkbox" wire:model.live="useRag" class="sr-only peer">
                        <div class="w-9 h-5 bg-zinc-200 hover:bg-zinc-300 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-[100%] peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-zinc-600 peer-checked:bg-indigo-600 transition-colors"></div>
                    </label>
                </div>

                {{-- Input row --}}
                <form @submit.prevent="if ($wire.isTyping) { return; } else { $wire.sendMessage() }" class="flex items-end gap-1.5">
                    {{-- Voice Input Mic Button --}}
                    <button
                        type="button"
                        @click="toggleVoiceInput()"
                        class="w-9 h-9 shrink-0 rounded-full flex items-center justify-center transition-all border border-zinc-200 dark:border-zinc-700"
                        :class="speechListening ? 'bg-rose-500 text-white animate-pulse border-rose-600' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 hover:text-indigo-600'"
                        :title="speechListening ? 'Sedang Mendengarkan Suara...' : 'Ketik Menggunakan Suara'"
                    >
                        <flux:icon.microphone class="w-4 h-4" />
                    </button>

                    {{-- Text field --}}
                    <div 
                        class="flex-1 bg-zinc-100 dark:bg-zinc-800 rounded-3xl px-3.5 py-2 flex items-end min-h-[36px] transition-all"
                        :class="{'opacity-50 cursor-not-allowed bg-zinc-200 dark:bg-zinc-800/60': $wire.isTyping}"
                    >
                        <textarea
                            wire:model.live="newMessage"
                            :disabled="$wire.isTyping"
                            class="w-full bg-transparent !border-none !border-0 !ring-0 !outline-none !shadow-none !p-0 text-[12px] focus:ring-0 focus:border-none focus:outline-none resize-none text-zinc-800 dark:text-zinc-200 placeholder-zinc-500 max-h-[80px] leading-snug disabled:cursor-not-allowed disabled:opacity-60"
                            style="box-shadow: none;"
                            :placeholder="$wire.isTyping ? 'AI sedang memproses jawaban...' : 'Ketik pesan...'"
                            rows="1"
                            x-data
                            x-init="
                                $el.style.height = '18px';
                                $el.addEventListener('input', function() {
                                    this.style.height = '18px';
                                    this.style.height = Math.min(this.scrollHeight, 80) + 'px';
                                });
                                Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                                    succeed(() => {
                                        if (!$wire.newMessage) {
                                            $el.style.height = '18px';
                                        }
                                    });
                                });
                            "
                        ></textarea>
                    </div>

                    {{-- Single Dynamic Send / Stop Button (Prevents DOM duplication) --}}
                    <button
                        :type="$wire.isTyping ? 'button' : 'submit'"
                        @click="if ($wire.isTyping) { $wire.stopAiResponse(); }"
                        class="w-9 h-9 shrink-0 rounded-full flex items-center justify-center transition-all shadow-sm disabled:opacity-40"
                        :class="$wire.isTyping ? 'bg-rose-600 hover:bg-rose-700 text-white animate-pulse' : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
                        :disabled="!$wire.isTyping && $wire.newMessage.trim() === ''"
                        :title="$wire.isTyping ? 'Hentikan Proses AI' : 'Kirim Pesan'"
                    >
                        <div x-show="$wire.isTyping">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                <rect x="6" y="6" width="12" height="12" rx="2" />
                            </svg>
                        </div>
                        <div x-show="!$wire.isTyping">
                            <flux:icon.paper-airplane class="w-4 h-4" />
                        </div>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => {
                let c = document.getElementById('ai-chat-container');
                if(c) c.scrollTop = c.scrollHeight;
            });
        });
    </script>
</div>