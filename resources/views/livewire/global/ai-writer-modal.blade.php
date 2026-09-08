<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public $show = false;
    
    // AI Generation
    public $isGeneratingAi = false;
    public $aiPrompt = '';
    public $useRagContext = false;
    public $selectedProvider = 'Gemini';

    #[On('open-ai-writer-modal')]
    public function openModal()
    {
        $this->reset(['aiPrompt', 'useRagContext', 'isGeneratingAi']);
        $this->show = true;
    }

    public function with()
    {
        $aiProvidersJson = \App\Models\Setting::where('key', 'ai_providers')->value('value');
        $providersList = $aiProvidersJson ? json_decode($aiProvidersJson, true) : [];
        
        $allProviders = [];
        foreach ($providersList as $provider) {
            if (!empty(trim($provider['name'] ?? ''))) {
                $allProviders[] = trim($provider['name']);
            }
        }
        
        // Default jika kosong
        if (empty($allProviders)) {
            $allProviders = ['OpenAI', 'Anthropic', 'Gemini'];
        }

        return [
            'providers' => $allProviders
        ];
    }

    public function generateWithAi()
    {
        $this->validate([
            'aiPrompt' => 'required|min:5',
            'selectedProvider' => 'required'
        ]);

        $this->isGeneratingAi = true;

        try {
            // Mengambil API Key dari Pengaturan Integrasi
            $aiProvidersJson = \App\Models\Setting::where('key', 'ai_providers')->value('value');
            $aiProviders = $aiProvidersJson ? json_decode($aiProvidersJson, true) : [];
            
            $apiKey = null;
            $providerName = strtolower(trim($this->selectedProvider));
            
            foreach ($aiProviders as $provider) {
                if (isset($provider['name']) && strtolower(trim($provider['name'])) === $providerName) {
                    $apiKey = trim($provider['key'] ?? '');
                    break;
                }
            }

            if (!$apiKey && $providerName === 'gemini') {
                $apiKey = env('GEMINI_API_KEY'); // Fallback ke .env
            }

            if (!$apiKey) {
                throw new \Exception('API Key untuk provider ' . $this->selectedProvider . ' belum diatur. Silakan isi di menu Pengaturan > Integrasi.');
            }
            
            $ragContextText = "";
            if ($this->useRagContext) {
                $vectorService = app(\App\Services\VectorSearchService::class);
                $relevantData = $vectorService->search($this->aiPrompt, 5); // Ambil Top 5
                
                if (count($relevantData) > 0) {
                    $ragContextText = "\n\nBerikut adalah data dari database internal kami yang mungkin relevan untuk menjawab permintaan ini:\n";
                    foreach ($relevantData as $index => $data) {
                        $ragContextText .= ($index + 1) . ". Sumber (" . $data['model_type'] . " ID " . $data['model_id'] . "): " . $data['content_text'] . "\n";
                    }
                } else {
                    $ragContextText = "\n\n(Tidak ada data internal yang ditemukan terkait permintaan ini.)\n";
                }
            }

            $systemPrompt = "Kamu adalah asisten penulis yang profesional. Buatkan teks/artikel lengkap dalam bahasa Indonesia berdasarkan instruksi yang diberikan pengguna. " . $ragContextText . " Gunakan tag HTML yang sesuai (seperti <h1>, <h2>, <p>, <ul>, <li>, <strong>, <table>, <tr>, <td>) agar siap dirender di rich editor. Jika dirasa perlu, sertakan tabel (<table>) untuk data perbandingan, dan sisipkan tag <img> menggunakan layanan placeholder (contoh: <img src=\"https://placehold.co/800x400/f3f4f6/6b7280.png?text=Ilustrasi+Topik\" alt=\"Deskripsi\" style=\"width:100%; border-radius:8px;\">) sebagai ilustrasi. Jangan gunakan markdown (seperti ```html), langsung kembalikan raw HTML-nya saja.";
            $userPrompt = $this->aiPrompt;
            
            $result = null;

            if ($providerName === 'gemini') {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=' . $apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemPrompt . "\n\nInstruksi Pengguna: '" . $userPrompt . "'"]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                    ]
                ]);

                if ($response->successful()) {
                    $result = $response->json('candidates.0.content.parts.0.text');
                } else {
                    throw new \Exception($response->json('error.message') ?? 'Gagal menghubungi server Gemini API');
                }
            } elseif (str_contains($providerName, 'openai') || str_contains($providerName, 'gpt')) {
                $response = \Illuminate\Support\Facades\Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt]
                    ],
                    'temperature' => 0.7,
                ]);

                if ($response->successful()) {
                    $result = $response->json('choices.0.message.content');
                } else {
                    throw new \Exception($response->json('error.message') ?? 'Gagal menghubungi OpenAI API');
                }
            } elseif (str_contains($providerName, 'anthropic') || str_contains($providerName, 'claude')) {
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json'
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-5-sonnet-20241022',
                    'max_tokens' => 4000,
                    'system' => $systemPrompt,
                    'messages' => [['role' => 'user', 'content' => $userPrompt]]
                ]);

                if ($response->successful()) {
                    $result = $response->json('content.0.text');
                } else {
                    throw new \Exception($response->json('error.message') ?? 'Gagal menghubungi Anthropic API');
                }
            } else {
                throw new \Exception('Provider AI "' . $this->selectedProvider . '" tidak didukung.');
            }

            // Membersihkan markdown wrapper jika AI masih membandel mengirimkannya
            $result = preg_replace('/^```html\s*/i', '', $result);
            $result = preg_replace('/```$/', '', $result);
            
            $content = trim($result);
            
            // Beritahu rich-editor untuk memperbarui isinya
            $this->dispatch('ai-content-generated', content: $content);
            
            $this->show = false;
            $this->aiPrompt = ''; // Reset prompt
            \Flux\Flux::toast('Teks berhasil dibuat dengan ' . $this->selectedProvider . '!');
        } catch (\Exception $e) {
            \Flux\Flux::toast(text: 'Error: ' . $e->getMessage(), variant: 'danger');
        }

        $this->isGeneratingAi = false;
    }
};
?>
<div>
    <div x-data="{ show: @entangle('show') }" style="display: none;" x-show="show">
        <!-- Backdrop -->
        <div x-show="show" class="fixed inset-0 z-[210] bg-zinc-900/50 backdrop-blur-sm transition-opacity" x-on:click="show = false"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
        
        <!-- Modal Container -->
        <div x-show="show" class="fixed inset-0 z-[210] overflow-y-auto" @click="show = false">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <!-- Modal Panel -->
                <div x-show="show" @click.stop
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative transform rounded-xl bg-white dark:bg-zinc-900 text-left shadow-xl transition-all w-full md:w-[600px] max-w-full border border-zinc-200 dark:border-zinc-800"
                     wire:ignore.self>
                    <div class="p-6">
                        <form wire:submit="generateWithAi" class="space-y-6">
                            <div class="flex justify-between items-start">
                                <div>
                                    <flux:heading size="lg" class="flex items-center gap-2">
                                        <flux:icon.sparkles class="w-5 h-5 text-amber-500" />
                                        Asisten Penulis AI
                                    </flux:heading>
                                    <flux:subheading>Berikan instruksi yang jelas agar AI dapat menuliskan teks sesuai keinginan Anda.</flux:subheading>
                                </div>
                                <div class="w-32 shrink-0">
                                    <flux:select wire:model="selectedProvider" size="sm">
                                        @foreach($providers as $provider)
                                            <flux:select.option value="{{ $provider }}">{{ $provider }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            </div>

                            <flux:textarea 
                                wire:model="aiPrompt" 
                                label="Prompt / Instruksi Penulisan" 
                                placeholder="Contoh: Buatkan artikel santai sebanyak 4 paragraf tentang pentingnya menjaga kebersihan gudang. Berikan tips-tips praktis di dalamnya." 
                                rows="4" 
                                required 
                            />
                            
                            <flux:checkbox wire:model="useRagContext" label="Gunakan Data Database (RAG)" description="Cari data yang relevan di seluruh sistem (Karyawan, Barang, Transaksi, dll) sebelum menulis." />

                            <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 mt-2 w-full">
                                <flux:button variant="ghost" class="w-full sm:w-auto" wire:click="$set('show', false)" wire:loading.attr="disabled" wire:target="generateWithAi">Batal</flux:button>
                                <flux:button type="submit" variant="primary" class="bg-amber-600 hover:bg-amber-700 text-white border-none w-full sm:w-auto" wire:loading.attr="disabled" wire:target="generateWithAi">
                                    <span wire:loading.remove wire:target="generateWithAi">Tulis Sekarang</span>
                                    <span wire:loading wire:target="generateWithAi">Sedang Berpikir...</span>
                                </flux:button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
