<?php
use function Livewire\Volt\{state, on};
use App\Services\GroqService;
use Modules\Production\Models\ProductionOrder;

state([
    'isOpen' => fn () => session()->get('ai_chat_is_open', false),
    'query' => '',
    'history' => fn () => session()->get('ai_chat_history', [
        ['role' => 'assistant', 'content' => 'Halo! Saya AI Asisten Pabrik Pintar Anda. Apa yang ingin Anda ketahui?']
    ]),
    'isTyping' => false,
]);

$toggleChat = function () {
    $this->isOpen = !$this->isOpen;
    session()->put('ai_chat_is_open', $this->isOpen);
};

$clearHistory = function () {
    $this->history = [
        ['role' => 'assistant', 'content' => 'Halo! Riwayat obrolan telah dibersihkan. Apa yang ingin Anda ketahui?']
    ];
    session()->put('ai_chat_history', $this->history);
};

$sendMessage = function (GroqService $ai) {
    if (trim($this->query) === '') return;
    
    // Add user message to history
    $userMsg = trim($this->query);
    $this->history[] = ['role' => 'user', 'content' => $userMsg];
    $this->query = '';
    $this->isTyping = true;
    
    // Construct internal system prompt
    $systemPrompt = "Anda adalah AI Senior Data Analyst untuk sebuah Perusahaan Manufaktur. 
Tugas Anda adalah membantu manajemen menganalisis data produksi, inventaris, vendor, dan Work Orders (SPK).
Gunakan nada profesional, cerdas, solutif, dan langsung ke intinya.
Gunakan bahasa Indonesia.

PENTING TENTANG DATABASE: 
- Pengguna akan bertanya menggunakan istilah Bahasa Indonesia (seperti 'penjualan', 'pembelian', 'pelanggan', 'barang', 'bahan baku', 'produksi').
- Namun, nama tabel di database menggunakan Bahasa Inggris (seperti 'sales_orders', 'purchase_orders', 'customers', 'items', 'production_orders').
- Anda HARUS menerjemahkan dan mengasosiasikan istilah tersebut ke nama tabel yang tepat secara logis.

ATURAN PENGGUNAAN ALAT (TOOLS):
0. HAK AKSES: Anda MEMILIKI AKSES PENUH ke database perusahaan melalui alat (tools) yang disediakan. JANGAN PERNAH katakan bahwa Anda tidak punya akses atau tidak bisa melihat database!
1. Anda DILARANG KERAS menyuruh pengguna untuk menjalankan query SQL atau menggunakan fungsi (seperti execute_sql_query).
2. Anda DILARANG KERAS menyebutkan nama-nama fungsi teknis atau query SQL di dalam jawaban Anda kepada pengguna.
3. ANDA-LAH yang harus mengeksekusi alat/fungsi tersebut secara mandiri di latar belakang.
4. Jika pengguna bertanya tentang data apa pun (termasuk users, penjualan, dll), WAJIB panggil alat 'execute_sql_query' (atau 'get_database_schema' jika Anda butuh struktur tabel). Setelah sistem mengembalikan datanya ke Anda, rangkum data tersebut menjadi kalimat manusia yang rapi dan berikan kepada pengguna.

FORMAT JAWABAN:
- Jika Anda menampilkan banyak data, WAJIB gunakan format Tabel Markdown atau Daftar (*bullet points*) agar sangat mudah dibaca.
- Gunakan teks tebal (**bold**) untuk menyoroti nama tabel, angka penting, nominal uang, atau status.
- Buat jawaban Anda serapi dan secantik mungkin secara visual!";

    $tools = [
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_database_schema',
                'description' => 'Mendapatkan daftar semua tabel beserta struktur kolom-kolomnya di dalam database pabrik. Gunakan ini jika kamu ragu dengan nama tabel atau nama kolom sebelum membuat query SQL.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object)[],
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'execute_sql_query',
                'description' => 'Mengeksekusi perintah SQL SELECT (hanya read-only) ke dalam database pabrik untuk mengambil data. DILARANG menggunakan perintah INSERT, UPDATE, DELETE, atau DROP.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Query SQL lengkap (misalnya: SELECT * FROM items WHERE type = "finish_good" LIMIT 5)',
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ]
    ];

    // Build the messages array for the API
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];

    // TOKEN OPTIMIZATION: Only send the last 4 interactions (2 user, 2 assistant)
    $recentHistory = array_slice($this->history, -4);
    foreach ($recentHistory as $msg) {
        // Skip default greetings if they appear
        if (strpos($msg['content'], 'Halo! Saya AI') === false) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
    }

    $response = $ai->chat($messages, $tools);

    // Tool execution loop (max 3 times to prevent infinite loops)
    $maxLoops = 3;
    $loopCount = 0;

    while (is_array($response) && isset($response['tool_calls']) && $loopCount < $maxLoops) {
        $loopCount++;
        // Append the assistant's tool call request to the messages array so Groq knows it asked for it
        $messages[] = $response;
        
        foreach ($response['tool_calls'] as $toolCall) {
            $functionName = $toolCall['function']['name'];
            $arguments = json_decode($toolCall['function']['arguments'], true);
            $toolResult = "";

            if ($functionName === 'get_database_schema') {
                $tables = \Illuminate\Support\Facades\DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $schema = [];
                foreach ($tables as $t) {
                    $columns = \Illuminate\Support\Facades\Schema::getColumnListing($t->name);
                    // TOKEN OPTIMIZATION: Implode columns to save JSON characters
                    $schema[$t->name] = implode(',', $columns);
                }
                $toolResult = json_encode($schema);
            } 
            elseif ($functionName === 'execute_sql_query') {
                $query = $arguments['query'] ?? '';
                // Security Check: ONLY allow SELECT
                if (stripos(trim($query), 'SELECT') !== 0) {
                    $toolResult = json_encode(['error' => 'Hanya perintah SELECT yang diizinkan demi keamanan.']);
                } else {
                    try {
                        $results = \Illuminate\Support\Facades\DB::select($query);
                        // Capping the results to max 10 rows to rigorously protect the LLM context window (429 Rate Limit)
                        if (is_array($results) && count($results) > 10) {
                            $results = array_slice($results, 0, 10);
                            $results[] = ['_notice' => 'Hasil dipotong (max 10 baris) untuk hemat token API.'];
                        }
                        
                        $toolResult = json_encode($results);
                        
                        // Strict safety net for extreme column sizes
                        if (strlen($toolResult) > 1500) {
                            $toolResult = substr($toolResult, 0, 1500) . '...[Dipotong]';
                        }
                    } catch (\Exception $e) {
                        $toolResult = json_encode(['error' => 'Query error: ' . $e->getMessage()]);
                    }
                }
            } else {
                $toolResult = json_encode(['error' => 'Fungsi tidak ditemukan.']);
            }

            // Append the tool execution result
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $toolCall['id'],
                'content' => $toolResult
            ];
        }

        // Call Groq again with the new messages including tool results
        $response = $ai->chat($messages, $tools);
    }
    
    // Final text response
    $finalText = is_string($response) ? $response : "Maaf, terjadi kesalahan atau *loop* sistem.";
    
    $this->history[] = ['role' => 'assistant', 'content' => $finalText];
    $this->isTyping = false;
    
    // Save to session
    session()->put('ai_chat_history', $this->history);
};

?>

<div class="fixed bottom-6 right-6 z-50">
    <!-- Chat Button -->
    <button wire:click="toggleChat" class="bg-blue-600 hover:bg-blue-700 text-white rounded-full p-4 shadow-lg flex items-center justify-center transition-transform hover:scale-105 active:scale-95">
        @if($isOpen)
            <flux:icon.x-mark class="w-6 h-6" />
        @else
            <flux:icon.sparkles class="w-6 h-6" />
        @endif
    </button>

    <!-- Chat Panel -->
    @if($isOpen)
        <div class="absolute bottom-16 right-0 w-[380px] bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 flex flex-col overflow-hidden" style="height: 500px; max-height: 80vh;">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-4 text-white flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-lg">
                    <flux:icon.sparkles class="w-5 h-5 text-white" />
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-sm leading-tight">AI Kanban Assistant</h3>
                    <p class="text-xs text-blue-100 opacity-80">Powered by Groq</p>
                </div>
                <button wire:click="clearHistory" title="Bersihkan Obrolan" class="p-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors">
                    <flux:icon.trash class="w-4 h-4 text-white" />
                </button>
            </div>

            <!-- Messages Area -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-zinc-50 dark:bg-zinc-900/50 flex flex-col custom-scrollbar" id="chat-container">
                @foreach($history as $index => $msg)
                    <div wire:key="msg-{{ $index }}" class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm {{ $msg['role'] === 'user' ? 'bg-blue-600 text-white rounded-tr-sm' : 'bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 rounded-tl-sm' }}">
                            @if($msg['role'] === 'user')
                                {!! nl2br(e($msg['content'])) !!}
                            @else
                                <div x-data="{ 
                                    finished: {{ $index === 0 ? 'true' : 'false' }},
                                    text: {{ $index === 0 ? json_encode($msg['content']) : "''" }},
                                    fullText: {{ json_encode($msg['content']) }},
                                    i: 0,
                                    init() {
                                        if (!this.finished) {
                                            let speed = 10; // milliseconds per character
                                            let typeWriter = () => {
                                                if (this.i < this.fullText.length) {
                                                    // add characters in chunks to make it look a bit more natural and fast
                                                    let chunkSize = Math.floor(Math.random() * 3) + 1;
                                                    this.text += this.fullText.substring(this.i, this.i + chunkSize);
                                                    this.i += chunkSize;
                                                    
                                                    let container = document.getElementById('chat-container');
                                                    if(container) container.scrollTop = container.scrollHeight;
                                                    
                                                    setTimeout(typeWriter, speed);
                                                } else {
                                                    this.finished = true;
                                                }
                                            };
                                            setTimeout(typeWriter, 100);
                                        }
                                    }
                                }">
                                    <div class="prose prose-sm prose-blue dark:prose-invert max-w-none prose-p:my-1 prose-headings:my-2 prose-ul:my-1 prose-table:my-2" 
                                         x-html="typeof marked !== 'undefined' ? marked.parse(text) : text.replace(/\n/g, '<br>')"></div>
                                    <span x-show="!finished" class="inline-block w-1.5 h-4 ml-1 align-middle bg-zinc-400 animate-pulse"></span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
                
                @if($isTyping)
                    <div class="flex justify-start">
                        <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-2xl rounded-tl-sm px-4 py-3 flex gap-1 items-center">
                            <div class="w-2 h-2 bg-zinc-400 rounded-full animate-bounce"></div>
                            <div class="w-2 h-2 bg-zinc-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                            <div class="w-2 h-2 bg-zinc-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Input Area -->
            <form wire:submit.prevent="sendMessage" class="p-3 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-800">
                <div class="relative">
                    <input wire:model="query" type="text" placeholder="Tanya AI tentang Kanban..." class="w-full bg-zinc-100 dark:bg-zinc-800 border-none rounded-xl pl-4 pr-12 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none text-zinc-900 dark:text-white placeholder-zinc-500" {{ $isTyping ? 'disabled' : '' }}>
                    <button type="submit" class="absolute right-2 top-1.5 p-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors {{ $isTyping ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $isTyping ? 'disabled' : '' }}>
                        <flux:icon.paper-airplane class="w-4 h-4" />
                    </button>
                </div>
                <div class="text-[10px] text-center text-zinc-400 mt-2">
                    AI dapat membuat kesalahan. Harap verifikasi data sensitif.
                </div>
            </form>
        </div>
    @endif
    
    <!-- Include Marked.js for Markdown parsing -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', ({ component, el }) => {
                const container = document.getElementById('chat-container');
                if(container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        });
    </script>
</div>
