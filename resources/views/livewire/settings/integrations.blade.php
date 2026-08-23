<?php

use Livewire\Volt\Component;
use App\Models\Setting;
use App\Events\SettingUpdated;
use Illuminate\Support\Facades\Cache;

new class extends Component {
    public $clarityId = '';
    public $beamsInstanceId = '';
    public $beamsSecretKey = '';
    public $pusherAppId = '';
    public $pusherKey = '';
    public $pusherSecret = '';
    public $pusherCluster = '';
    public $fonnteToken = '';
    public $aiProviders = [];
    public $gudangHandlesShipping = false;
    public $enableAiChat = false;
    public $enablePusherSound = true;

    public $beamsTestStatus = null;
    public $channelsTestStatus = null;

    public function mount() {
        $this->clarityId = Setting::where('key', 'clarity_id')->value('value') ?? '';
        $this->beamsInstanceId = Setting::where('key', 'beams_instance_id')->value('value') ?? '';
        $this->beamsSecretKey = Setting::where('key', 'beams_secret_key')->value('value') ?? '';
        $this->pusherAppId = Setting::where('key', 'pusher_app_id')->value('value') ?? '';
        $this->pusherKey = Setting::where('key', 'pusher_key')->value('value') ?? '';
        $this->pusherSecret = Setting::where('key', 'pusher_secret')->value('value') ?? '';
        $this->pusherCluster = Setting::where('key', 'pusher_cluster')->value('value') ?? '';
        $this->fonnteToken = Setting::where('key', 'fonnte_token')->value('value') ?? '';
        
        $aiProvidersJson = Setting::where('key', 'ai_providers')->value('value');
        $this->aiProviders = $aiProvidersJson ? json_decode($aiProvidersJson, true) : [
            ['name' => 'OpenAI', 'key' => ''],
            ['name' => 'Anthropic', 'key' => ''],
            ['name' => 'Gemini', 'key' => ''],
        ];
        
        $this->gudangHandlesShipping = Setting::where('key', 'gudang_handles_shipping')->value('value') == '1';
        $this->enableAiChat = Setting::where('key', 'enable_ai_chat')->value('value') == '1';
        $this->enablePusherSound = Setting::where('key', 'enable_pusher_sound')->value('value') !== '0';
    }

    public array $aiTestStatus = [];

    public function testAiProvider($index) {
        if (!isset($this->aiProviders[$index])) return;

        $provider = $this->aiProviders[$index];
        $name = trim($provider['name'] ?? '');
        $key = trim($provider['key'] ?? '');

        if (empty($key)) {
            $this->aiTestStatus[$index] = 'error';
            \Flux::toast("API Key untuk provider {$name} belum diisi!", variant: 'danger');
            return;
        }

        try {
            $nameLower = strtolower($name);

            if (str_contains($nameLower, 'gemini') || empty($name)) {
                // Test Google Gemini API
                $candidateModels = ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-2.5-flash', 'gemini-3.6-flash'];
                $response = null;
                foreach ($candidateModels as $modelName) {
                    $response = \Illuminate\Support\Facades\Http::post("https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$key}", [
                        'contents' => [
                            ['role' => 'user', 'parts' => [['text' => 'Tes koneksi API, jawab dengan kata OK']]]
                        ]
                    ]);
                    if ($response->successful()) break;
                }

                if ($response && $response->successful()) {
                    $reply = $response->json('candidates.0.content.parts.0.text') ?? 'OK';
                    $this->aiTestStatus[$index] = 'success';
                    \Flux::toast("Koneksi {$name} Berhasil! Respons AI: {$reply}", variant: 'success');
                } else {
                    $errorDetail = ($response ? $response->json('error.message') : null) ?? 'Gagal menghubungi Gemini API';
                    $this->aiTestStatus[$index] = 'error';
                    \Flux::toast("Koneksi {$name} GAGAL: {$errorDetail}", variant: 'danger');
                }
            } elseif (str_contains($nameLower, 'openai')) {
                $response = \Illuminate\Support\Facades\Http::withToken($key)->get('https://api.openai.com/v1/models');
                if ($response->successful()) {
                    $this->aiTestStatus[$index] = 'success';
                    \Flux::toast("Koneksi {$name} Berhasil!", variant: 'success');
                } else {
                    $errorDetail = $response->json('error.message') ?? 'Invalid API key';
                    $this->aiTestStatus[$index] = 'error';
                    \Flux::toast("Koneksi {$name} GAGAL: {$errorDetail}", variant: 'danger');
                }
            } elseif (str_contains($nameLower, 'anthropic') || str_contains($nameLower, 'claude')) {
                $candidateClaudeModels = ['claude-haiku-4-5-20251001', 'claude-sonnet-4-5-20250929', 'claude-sonnet-4-6', 'claude-sonnet-5', 'claude-3-5-haiku-20241022'];
                $response = null;
                foreach ($candidateClaudeModels as $modelName) {
                    $response = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
                        'x-api-key' => $key,
                        'anthropic-version' => '2023-06-01',
                        'content-type' => 'application/json'
                    ])->post('https://api.anthropic.com/v1/messages', [
                        'model' => $modelName,
                        'max_tokens' => 20,
                        'messages' => [['role' => 'user', 'content' => 'hi']]
                    ]);

                    if ($response->successful()) break;
                }

                if ($response && $response->successful()) {
                    $this->aiTestStatus[$index] = 'success';
                    \Flux::toast("Koneksi {$name} Berhasil!", variant: 'success');
                } else {
                    $errorDetail = ($response ? $response->json('error.message') : null) ?? 'Invalid API key / model';
                    $this->aiTestStatus[$index] = 'error';
                    \Flux::toast("Koneksi {$name} GAGAL: {$errorDetail}", variant: 'danger');
                }
            } else {
                $this->aiTestStatus[$index] = 'success';
                \Flux::toast("Format API Key {$name} tersimpan.", variant: 'success');
            }
        } catch (\Exception $e) {
            $this->aiTestStatus[$index] = 'error';
            \Flux::toast("Koneksi {$name} Error: " . $e->getMessage(), variant: 'danger');
        }
    }

    public function addAiProvider() {
        $this->aiProviders[] = ['name' => '', 'key' => ''];
    }

    public function reindexKnowledgeBase() {
        try {
            \Illuminate\Support\Facades\Artisan::call('ai:index', ['model_class' => 'all']);
            $count = \App\Models\AiKnowledgeBase::count();
            \Flux::toast("Indeks Data RAG AI Berhasil! Total {$count} data ERP telah terindeks.", variant: 'success');
        } catch (\Exception $e) {
            \Flux::toast('Gagal melakukan indeks RAG: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function removeAiProvider($index) {
        unset($this->aiProviders[$index]);
        $this->aiProviders = array_values($this->aiProviders);
    }

    public function save() {
        $settings = [
            'clarity_id' => $this->clarityId,
            'beams_instance_id' => $this->beamsInstanceId,
            'beams_secret_key' => $this->beamsSecretKey,
            'pusher_app_id' => $this->pusherAppId,
            'pusher_key' => $this->pusherKey,
            'pusher_secret' => $this->pusherSecret,
            'pusher_cluster' => $this->pusherCluster,
            'fonnte_token' => $this->fonnteToken,
            'ai_providers' => json_encode($this->aiProviders),
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
        
        $shippingValue = $this->gudangHandlesShipping ? '1' : '0';
        Setting::updateOrCreate(
            ['key' => 'gudang_handles_shipping'],
            ['value' => $shippingValue]
        );

        $enableAiChatValue = $this->enableAiChat ? '1' : '0';
        Setting::updateOrCreate(
            ['key' => 'enable_ai_chat'],
            ['value' => $enableAiChatValue]
        );

        $soundValue = $this->enablePusherSound ? '1' : '0';
        Setting::updateOrCreate(
            ['key' => 'enable_pusher_sound'],
            ['value' => $soundValue]
        );

        Cache::put('setting_gudang_handles_shipping', $shippingValue, now()->addHour());
        Cache::put('setting_enable_ai_chat', $enableAiChatValue, now()->addHour());
        Cache::forget('setting_clarity_id');
        Cache::forget('setting_enable_pusher_sound');
        Cache::forget('app_integration_settings');

        $this->js("window.ENABLE_PUSHER_SOUND = " . ($this->enablePusherSound ? 'true' : 'false'));

        SettingUpdated::safeDispatch('gudang_handles_shipping', $shippingValue);
        SettingUpdated::safeDispatch('enable_ai_chat', $enableAiChatValue);

        \Flux::toast('Pengaturan berhasil disimpan!');
    }

    public function updatedEnablePusherSound($value) {
        $soundValue = $value ? '1' : '0';
        Setting::updateOrCreate(
            ['key' => 'enable_pusher_sound'],
            ['value' => $soundValue]
        );
        Cache::forget('setting_enable_pusher_sound');
        $this->js("window.ENABLE_PUSHER_SOUND = " . ($value ? 'true' : 'false'));
    }

    public function testPusherBeams() {
        if (empty($this->beamsInstanceId) || empty($this->beamsSecretKey)) {
            $this->beamsTestStatus = 'error';
            \Flux::toast('Kredensial Pusher Beams belum lengkap!', variant: 'danger');
            return;
        }

        try {
            if (!class_exists(\Pusher\PushNotifications\PushNotifications::class)) {
                $this->beamsTestStatus = 'error';
                \Flux::toast('Package pusher/pusher-push-notifications belum diinstall.', variant: 'danger');
                return;
            }

            $class = \Pusher\PushNotifications\PushNotifications::class;
            $beamsClient = new $class([
                "instanceId" => $this->beamsInstanceId,
                "secretKey" => $this->beamsSecretKey,
            ]);
            
            $beamsClient->publishToInterests(
                array("all-users", "debug-test"),
                array("web" => array("notification" => array(
                    "title" => "Test Koneksi Beams",
                    "body" => "Koneksi Pusher Beams Berhasil!"
                )))
            );
            $this->beamsTestStatus = 'success';
        } catch (\Exception $e) {
            $this->beamsTestStatus = 'error';
            \Flux::toast('Koneksi Beams GAGAL: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function testPusherChannels() {
        if (empty($this->pusherAppId) || empty($this->pusherKey) || empty($this->pusherSecret) || empty($this->pusherCluster)) {
            $this->channelsTestStatus = 'error';
            \Flux::toast('Kredensial Pusher Channels belum lengkap!', variant: 'danger');
            return;
        }

        try {
            if (!class_exists(\Pusher\Pusher::class)) {
                $this->channelsTestStatus = 'error';
                \Flux::toast('Package pusher/pusher-php-server belum diinstall.', variant: 'danger');
                return;
            }

            $pusher = new \Pusher\Pusher(
                $this->pusherKey,
                $this->pusherSecret,
                $this->pusherAppId,
                array('cluster' => $this->pusherCluster, 'useTLS' => true)
            );
            
            $pusher->trigger('debug-test', 'test-event', ['message' => 'success']);
            $this->channelsTestStatus = 'success';
        } catch (\Exception $e) {
            $this->channelsTestStatus = 'error';
            \Flux::toast('Koneksi Channels GAGAL: ' . $e->getMessage(), variant: 'danger');
        }
    }
};
?>

<x-pages::settings.layout :heading="__('Pengaturan & Integrasi')" :subheading="__('Kelola integrasi pihak ketiga dan pengaturan operasional.')">
    
    <form wire:submit="save" class="space-y-8">
        
        <!-- Bagian Integrasi -->
        <div class="space-y-4">
            <flux:heading size="lg">Microsoft Clarity</flux:heading>
            <flux:subheading>
                Masukkan Project ID (Tracking ID) dari Microsoft Clarity untuk merekam sesi dan interaksi pengguna di aplikasi.
            </flux:subheading>

            <flux:input 
                wire:model="clarityId" 
                label="Clarity Tracking ID" 
                placeholder="Contoh: x4qbnvo0dw" 
                description="Biarkan kosong untuk menonaktifkan pelacakan Microsoft Clarity."
            />
        </div>

        <flux:separator />

        <!-- AI Assistant (LLM) -->
        <div class="space-y-4">
            <flux:heading size="lg">AI Assistant (Kredensial Model)</flux:heading>
            <flux:subheading>
                Tambahkan provider AI sesuai kebutuhan Anda. Dapatkan API Key secara gratis dari: 
                <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-indigo-600 dark:text-indigo-400 underline font-medium hover:text-indigo-500">Google AI Studio (Gemini)</a>, 
                <a href="https://platform.openai.com/api-keys" target="_blank" class="text-indigo-600 dark:text-indigo-400 underline font-medium hover:text-indigo-500">OpenAI API Keys</a>, atau 
                <a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-indigo-600 dark:text-indigo-400 underline font-medium hover:text-indigo-500">Anthropic Console (Claude)</a>.
            </flux:subheading>

            <flux:switch wire:model="enableAiChat" label="Tampilkan Chat AI" description="Tampilkan atau sembunyikan fitur asisten Chat AI di antarmuka aplikasi." class="mb-4" />

            <div class="space-y-4">
                @foreach($aiProviders as $index => $provider)
                    <div class="flex items-center gap-3">
                        <div class="w-1/3">
                            <flux:input wire:model="aiProviders.{{ $index }}.name" placeholder="Nama Provider (Misal: Gemini)" />
                        </div>
                        <div class="flex-1">
                            <flux:input wire:model="aiProviders.{{ $index }}.key" type="password" placeholder="API Key" />
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <flux:button 
                                variant="outline" 
                                icon="bolt" 
                                size="sm"
                                wire:click="testAiProvider({{ $index }})" 
                                wire:loading.attr="disabled"
                                title="Tes Koneksi API"
                            >
                                Tes Koneksi
                            </flux:button>
                            <flux:button variant="danger" icon="trash" size="sm" wire:click="removeAiProvider({{ $index }})" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <flux:button variant="subtle" icon="plus" wire:click="addAiProvider">Tambah Provider AI</flux:button>
            </div>

            {{-- RAG Knowledge Base Sync Section --}}
            @php
                $ragCount = \App\Models\AiKnowledgeBase::count();
                $ragLatest = \App\Models\AiKnowledgeBase::latest('updated_at')->value('updated_at');
            @endphp
            <div class="mt-4 p-4 bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 rounded-xl flex flex-col gap-3">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h5 class="text-xs font-bold text-indigo-900 dark:text-indigo-200 flex items-center gap-1.5">
                                <flux:icon.cpu-chip class="w-4 h-4 text-indigo-500" /> Basis Pengetahuan RAG (Retrieval-Augmented Generation)
                            </h5>
                            @if($ragCount > 0)
                                <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-500/20 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Terindeks & Aktif
                                </span>
                            @else
                                <span class="bg-amber-500/10 text-amber-600 dark:text-amber-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-amber-500/20">
                                    Belum Diindeks
                                </span>
                            @endif
                        </div>
                        <p class="text-[11px] text-zinc-600 dark:text-zinc-400 mt-0.5">
                            Klik tombol ini untuk mengindeks seluruh data lama (Barang, Sales Order, Purchase Order, Keuangan, Customer, Supplier) agar AI dapat langsung membaca dan menjawab data transaksi bisnis Anda.
                        </p>
                    </div>
                    <flux:button variant="primary" icon="arrow-path" size="sm" wire:click="reindexKnowledgeBase" wire:loading.attr="disabled" class="shrink-0">
                        <span wire:loading.remove wire:target="reindexKnowledgeBase">Indeks Data RAG Sekarang</span>
                        <span wire:loading wire:target="reindexKnowledgeBase">Mengindeks Data...</span>
                    </flux:button>
                </div>

                {{-- Live RAG Status Info --}}
                <div class="flex flex-wrap items-center gap-4 pt-2 border-t border-indigo-100/80 dark:border-indigo-900/40 text-[11px] text-zinc-600 dark:text-zinc-400">
                    <div class="flex items-center gap-1.5">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Total Terindeks:</span>
                        <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-white dark:bg-zinc-900 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700 shadow-xs">{{ number_format($ragCount) }} Record</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Terakhir Sinkron:</span>
                        <span class="font-mono text-zinc-600 dark:text-zinc-400">
                            {{ $ragLatest ? \Carbon\Carbon::parse($ragLatest)->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : 'Belum pernah' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <flux:separator />
        
        <!-- Fonnte (WhatsApp API) -->
        <div class="space-y-4">
            <flux:heading size="lg">Fonnte (WhatsApp API)</flux:heading>
            <flux:subheading>
                Konfigurasi Token Fonnte untuk mengirim notifikasi WhatsApp otomatis ke pelanggan atau tim internal.
            </flux:subheading>

            <flux:input 
                wire:model="fonnteToken" 
                type="password"
                label="Fonnte API Token" 
                placeholder="Masukkan Token Fonnte..." 
                description="Token ini didapatkan dari dashboard Fonnte Anda."
            />
        </div>

        <flux:separator />

        <!-- Pusher Beams (Push Notifications) -->
        <div class="space-y-4">
            <flux:heading size="lg">Pusher Beams (Push Notifications)</flux:heading>
            <flux:subheading>
                Konfigurasi Instance ID dan Secret Key untuk layanan notifikasi push dari Pusher Beams.
            </flux:subheading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input 
                    wire:model="beamsInstanceId" 
                    label="Beams Instance ID" 
                    placeholder="Misal: b4c5d6e7-..." 
                />
                <flux:input 
                    wire:model="beamsSecretKey" 
                    type="password"
                    label="Beams Secret Key" 
                    placeholder="Misal: 1A2B3C..." 
                />
            </div>
            <div class="mt-2" x-data="{ status: $wire.entangle('beamsTestStatus') }" x-init="$watch('status', value => { if (value) setTimeout(() => status = null, 3000) })">
                <flux:button wire:click="testPusherBeams" 
                             x-bind:class="status === 'success' ? '!bg-green-500 !text-white !border-green-500 hover:!bg-green-600' : (status === 'error' ? '!bg-red-500 !text-white !border-red-500 hover:!bg-red-600' : '')"
                             variant="outline" icon="bolt" size="sm" wire:loading.attr="disabled">
                    <span x-text="status === 'success' ? 'Berhasil terkoneksi!' : (status === 'error' ? 'Gagal terkoneksi!' : 'Test Koneksi Beams')"></span>
                </flux:button>
            </div>
        </div>

        <flux:separator />

        <!-- Pusher (Websockets) -->
        <div class="space-y-4">
            <flux:heading size="lg">Pusher (Websockets)</flux:heading>
            <flux:subheading>
                Pengaturan kredensial Pusher untuk fitur Realtime (Kanban, Notifikasi, Chat).
            </flux:subheading>

            <flux:switch wire:model="enablePusherSound" label="Efek Suara Realtime (Sound Notification)" description="Aktifkan atau matikan efek suara otomatis saat menerima pembaruan data atau notifikasi dari Pusher Channels." class="mb-4" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="pusherAppId" label="App ID" placeholder="Misal: 1234567" />
                <flux:input wire:model="pusherKey" label="App Key" placeholder="Misal: a1b2c3d4e5" />
                <flux:input wire:model="pusherSecret" type="password" label="App Secret" placeholder="Misal: f6g7h8i9j0" />
                <flux:input wire:model="pusherCluster" label="Cluster" placeholder="Misal: ap1" />
            </div>
            <div class="mt-2" x-data="{ status: $wire.entangle('channelsTestStatus') }" x-init="$watch('status', value => { if (value) setTimeout(() => status = null, 3000) })">
                <flux:button wire:click="testPusherChannels" 
                             x-bind:class="status === 'success' ? '!bg-green-500 !text-white !border-green-500 hover:!bg-green-600' : (status === 'error' ? '!bg-red-500 !text-white !border-red-500 hover:!bg-red-600' : '')"
                             variant="outline" icon="bolt" size="sm" wire:loading.attr="disabled">
                    <span x-text="status === 'success' ? 'Berhasil terkoneksi!' : (status === 'error' ? 'Gagal terkoneksi!' : 'Test Koneksi Channels')"></span>
                </flux:button>
            </div>
        </div>

        <flux:separator />

        <!-- Bagian Operasional -->
        <div class="space-y-4">
            <flux:heading size="lg">Operasional Gudang</flux:heading>
            <flux:subheading>
                Atur kewenangan dan alur kerja untuk tim gudang dan pemenuhan pesanan.
            </flux:subheading>

            <flux:switch wire:model="gudangHandlesShipping" label="Gudang Menangani Ekspedisi" description="Jika diaktifkan, tim Gudang bisa menginput resi dan menyerahkan pesanan ke kurir. Jika dinonaktifkan, urusan ekspedisi akan ditangani sepenuhnya oleh tim Sales/Admin." />
        </div>

        <div class="flex items-center gap-4 pt-4">
            <flux:button icon="check" variant="primary" type="submit"> Simpan Pengaturan </flux:button>
            <flux:text class="text-sm">Perubahan akan langsung diterapkan ke seluruh aplikasi.</flux:text>
        </div>
    </form>

</x-pages::settings.layout>

