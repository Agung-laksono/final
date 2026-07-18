<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\VectorSearchService;

new class extends Component
{
    public array $messages = [];
    public string $newMessage = '';
    public bool $useRag = false;
    public bool $isTyping = false;

    public function mount()
    {
        $userId = auth()->id() ?? 'guest';
        $this->messages = Cache::get("ai_chat_history_{$userId}", []);
    }

    public function clearChat()
    {
        $userId = auth()->id() ?? 'guest';
        Cache::forget("ai_chat_history_{$userId}");
        $this->messages = [];
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
        
        // Kirim event agar Livewire me-render bubble user dan typing indicator
        // sebelum mulai menghubungi API Google yang memakan waktu lama
        $this->dispatch('trigger-ai-response');
    }

    #[\Livewire\Attributes\On('trigger-ai-response')]
    public function generateAiResponse()
    {
        // Pastikan ada pesan user terakhir
        $lastMessage = end($this->messages);
        if (!$lastMessage || $lastMessage['role'] !== 'user') {
            $this->isTyping = false;
            return;
        }

        $promptText = $lastMessage['parts'][0]['text'];
        $userId = auth()->id() ?? 'guest';

        // Prepare context if RAG is used
        $contextText = "";
        if ($this->useRag) {
            try {
                $vectorService = app(VectorSearchService::class);
                $relevantData = $vectorService->search($promptText, 5);
                
                if (count($relevantData) > 0) {
                    $contextText = "\n\n[CONTEXT DARI DATABASE INTERNAL]:\n";
                    foreach ($relevantData as $index => $data) {
                        $contextText .= ($index + 1) . ". Sumber (" . $data['model_type'] . "): " . $data['content_text'] . "\n";
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors from VectorSearchService if not set up properly
            }
        }

        // Format history for Gemini API
        $historyForGemini = [];
        foreach ($this->messages as $msg) {
            $historyForGemini[] = $msg;
        }

        // Inject context to the last user message secretly before sending
        if ($contextText !== "") {
            $lastIndex = count($historyForGemini) - 1;
            $historyForGemini[$lastIndex]['parts'][0]['text'] .= $contextText . "\n\nJawab pertanyaan pengguna berdasarkan CONTEXT di atas jika relevan. Jika tidak ada di konteks, jawab dengan pengetahuan umummu.";
        }

        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            $this->messages[] = [
                'role' => 'model',
                'parts' => [['text' => 'Error: API Key Gemini belum diatur di .env']]
            ];
            $this->isTyping = false;
            return;
        }

        $systemInstruction = "Kamu adalah ROMLAH Asisten, AI pintar terintegrasi dalam sistem ERP perusahaan. 
Berikut panduan membaca data sistem ini:
1. sales_orders & sales_deliveries: Transaksi penjualan dan pengiriman barang.
2. purchase_orders & purchase_receipts: Pembelian ke vendor dan penerimaan barang di gudang.
3. inventory_items: Master data produk. Di dalamnya mencakup relasi ke Kategori Barang (Category) dan Tipe Barang (Type).
4. inventory_movements & inventory_warehouses: Riwayat mutasi perpindahan stok antar gudang.
5. finance_transactions & finance_accounts: Data arus kas, rekening, dan pembayaran.
6. Status & Pembayaran: 
   - Jika pembayaran berstatus 'pending', artinya pembayaran sedang 'menunggu konfirmasi'.
   - Status order umumnya meliputi: draft, pending (menunggu), processing (diproses), completed (selesai/lunas), dan cancelled (batal).

Tugas utamamu: Saat menerima data JSON mentah dari sistem RAG (Data Internal), terjemahkan data ID, Kategori, Tipe, maupun Status tersebut menjadi penjelasan bahasa manusia yang mengalir, mudah dipahami, singkat, namun profesional.

Gaya Penulisan yang WAJIB dipatuhi:
- Kamu adalah orang yang sangat terstruktur dalam menjelaskan sesuatu dan sangat rapi dalam melakukan klasifikasi data/informasi.
- Jadilah penulis yang sangat rapi. Jangan pernah memberikan paragraf panjang yang bertumpuk (wall of text).
- Gunakan DOUBLE ENTER (dua kali enter / baris kosong) untuk memisahkan setiap paragraf atau poin utama agar jaraknya terlihat sangat jelas.
- Pastikan selalu ada spasi setelah tanda titik (.) sebelum memulai kalimat baru.
- Gunakan list (bullet points) secara ekstensif jika menjelaskan lebih dari dua item atau saat melakukan klasifikasi.";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=' . $apiKey, [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemInstruction]
                    ]
                ],
                'contents' => $historyForGemini
            ]);

            if ($response->successful()) {
                $replyText = $response->json('candidates.0.content.parts.0.text') ?? 'Tidak ada respons.';
                
                // Tambahkan tanda khusus untuk pesan baru agar Alpine bisa me-memicu efek ngetik
                $this->messages[] = [
                    'role' => 'model',
                    'parts' => [['text' => $replyText]],
                    'is_new' => true 
                ];
                Cache::put("ai_chat_history_{$userId}", $this->messages, now()->addHours(24));
            } else {
                $errorMsg = $response->json('error.message') ?? 'Unknown error';
                $this->messages[] = [
                    'role' => 'model',
                    'parts' => [['text' => 'API Error: ' . $errorMsg]]
                ];
            }

        } catch (\Exception $e) {
            $this->messages[] = [
                'role' => 'model',
                'parts' => [['text' => 'Error: ' . $e->getMessage()]]
            ];
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
            if (e.target.closest('button') || e.target.closest('input') || e.target.closest('textarea')) return;
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
        }
    }"
    style="position: fixed; inset: 0; pointer-events: none; z-index: 9999;"
>
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
        class="w-10 h-10 lg:w-12 lg:h-12 bg-indigo-500/10 hover:bg-indigo-500/20 dark:bg-indigo-500/20 dark:hover:bg-indigo-500/30 backdrop-blur-md border border-indigo-500/30 rounded-full shadow-lg flex items-center justify-center text-indigo-600 dark:text-indigo-400 transition-colors pointer-events-auto"
        style="position: absolute; bottom: 24px; right: 24px;"
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
        :style="`left: ${posX}px; position: absolute; bottom: 85px;`"
        class="w-[340px] max-w-[calc(100vw-16px)] flex flex-col rounded-2xl shadow-[0_8px_40px_rgba(0,0,0,0.18)] overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 pointer-events-auto"
    >
        {{-- MESSENGER HEADER --}}
        <div
            @mousedown="startDrag($event)"
            @touchstart.passive="startDrag($event)"
            class="flex items-center gap-2.5 px-3 py-2.5 bg-white dark:bg-zinc-900 border-b border-zinc-100 dark:border-zinc-800 cursor-grab active:cursor-grabbing select-none shrink-0"
        >
            {{-- Avatar dengan status online --}}
            <div class="relative shrink-0">
                <div class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center text-white shadow-sm">
                    <flux:icon.sparkles class="w-4 h-4" />
                </div>
                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-white dark:border-zinc-900 rounded-full"></span>
            </div>

            {{-- Nama & status --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1">
                    <span class="font-semibold text-[13px] text-zinc-900 dark:text-white truncate">ROMLAH Asisten</span>
                    <flux:icon.chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                </div>
                <span class="text-[11px] text-green-500 font-medium">Aktif sekarang</span>
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center gap-0.5 shrink-0">
                <button wire:click="clearChat" title="Hapus Obrolan" class="w-8 h-8 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 transition-colors">
                    <flux:icon.trash class="w-4 h-4" />
                </button>
                <button @click="minimized = !minimized" title="Perkecil" class="w-8 h-8 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 transition-colors">
                    <flux:icon.minus class="w-4 h-4" />
                </button>
                <button @click="open = false" title="Tutup" class="w-8 h-8 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center text-zinc-500 dark:text-zinc-400 transition-colors">
                    <flux:icon.x-mark class="w-4 h-4" />
                </button>
            </div>
        </div>

        {{-- CHAT BODY (collapsible) --}}
        <div x-show="!minimized" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

            {{-- MESSAGES --}}
            <div
                id="ai-chat-container"
                class="overflow-y-auto px-3 py-3 space-y-1.5 bg-white dark:bg-zinc-900"
                style="height: 320px;"
            >
                @if(count($messages) === 0)
                    <div class="h-full flex flex-col items-center justify-center text-center pb-4">
                        <div class="w-14 h-14 rounded-full bg-indigo-600 flex items-center justify-center text-white shadow-md mb-3">
                            <flux:icon.sparkles class="w-7 h-7" />
                        </div>
                        <h4 class="font-semibold text-sm text-zinc-800 dark:text-zinc-200">AI Assistant</h4>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Gemini 3.5 Flash</p>
                        <p class="text-xs text-zinc-400 mt-3 max-w-[200px]">Tanyakan apa saja seputar data bisnis atau operasional Anda.</p>
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
                        <div class="max-w-[78%] px-3 py-2 text-[13px] leading-snug rounded-2xl
                            {{ $isUser
                                ? 'bg-indigo-600 text-white rounded-br-md'
                                : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 rounded-bl-md prose prose-sm dark:prose-invert max-w-none prose-p:mb-3 prose-p:leading-relaxed prose-ul:my-2 prose-li:my-0.5'
                            }}">
                            @if($isUser)
                                {{ $msg['parts'][0]['text'] }}
                            @else
                                @if(isset($msg['is_new']) && $msg['is_new'])
                                    <div x-data="{
                                        fullHtml: '',
                                        visibleHtml: '',
                                        init() {
                                            this.fullHtml = this.$refs.hiddenText.innerHTML;
                                            let i = 0, inTag = false, inEntity = false;
                                            let iv = setInterval(() => {
                                                if (i >= this.fullHtml.length) {
                                                    clearInterval(iv);
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

                                                // Update scroll secara halus agar tidak berkedip seperti TV jadul
                                                if (i % 8 === 0) {
                                                    let c = document.getElementById('ai-chat-container');
                                                    if(c) c.scrollTop = c.scrollHeight;
                                                }
                                            }, 25); // Kecepatan diatur di sini: 25 milidetik per huruf (lebih lambat & nyaman dibaca)
                                        }
                                    }">
                                        <div style="display:none" x-ref="hiddenText">{!! Str::markdown($msg['parts'][0]['text']) !!}</div>
                                        <div x-html="visibleHtml"></div>
                                    </div>
                                @else
                                    {!! Str::markdown($msg['parts'][0]['text']) !!}
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
                        <div class="bg-zinc-100 dark:bg-zinc-800 rounded-2xl rounded-bl-md px-3.5 py-3 flex gap-1 items-center">
                            <div class="w-1.5 h-1.5 bg-zinc-400 rounded-full animate-bounce"></div>
                            <div class="w-1.5 h-1.5 bg-zinc-400 rounded-full animate-bounce" style="animation-delay:.15s"></div>
                            <div class="w-1.5 h-1.5 bg-zinc-400 rounded-full animate-bounce" style="animation-delay:.3s"></div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- INPUT BAR (Facebook Messenger style) --}}
            <div class="px-2 py-2 border-t border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shrink-0">
                {{-- RAG Toggle (compact) --}}
                <div class="flex items-center justify-between px-1 mb-1.5">
                    <div class="flex items-center gap-1 text-zinc-400">
                        <flux:icon.cpu-chip class="w-3 h-3" />
                        <span class="text-[10px] font-medium">Data Internal</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer scale-[0.75] origin-right">
                        <input type="checkbox" wire:model.live="useRag" class="sr-only peer">
                        <div class="w-9 h-5 bg-zinc-200 hover:bg-zinc-300 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-[100%] peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-zinc-600 peer-checked:bg-indigo-600 transition-colors"></div>
                    </label>
                </div>

                {{-- Input row --}}
                <form wire:submit="sendMessage" class="flex items-end gap-1.5">
                    {{-- Text field --}}
                    <div class="flex-1 bg-zinc-100 dark:bg-zinc-800 rounded-3xl px-3.5 py-2 flex items-end min-h-[36px]">
                        <textarea
                            wire:model="newMessage"
                            class="w-full bg-transparent !border-none !border-0 !ring-0 !outline-none !shadow-none !p-0 text-[13px] focus:ring-0 focus:border-none focus:outline-none resize-none text-zinc-800 dark:text-zinc-200 placeholder-zinc-500 max-h-[80px] leading-snug"
                            style="box-shadow: none;"
                            placeholder="Aa"
                            rows="1"
                            x-data
                            x-init="$el.style.height='18px'; $el.addEventListener('input', function(){ this.style.height='18px'; this.style.height=Math.min(this.scrollHeight, 80)+'px'; })"
                            wire:keydown.enter.prevent="sendMessage"
                        ></textarea>
                    </div>

                    {{-- Send / Thumbs-up button --}}
                    <button
                        type="submit"
                        class="w-9 h-9 shrink-0 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center transition-all shadow-sm disabled:opacity-40"
                        wire:loading.attr="disabled" wire:target="sendMessage"
                    >
                        <flux:icon.paper-airplane class="w-4 h-4" />
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