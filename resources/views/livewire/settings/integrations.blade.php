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
    public $aiAssistantName = 'ROMLAH Asisten';
    public $aiCustomInstruction = '';
    public $gudangHandlesShipping = false;
    public $enableAiChat = false;
    public $enablePusherSound = true;

    public $waReportEnabled = false;
    public $waReportFrequency = 'daily';
    public $waReportHour = '23';
    public $waReportMinute = '59';
    public $waReportRecipients = '';

    public $beamsTestStatus = null;
    public $channelsTestStatus = null;
    public $fonnteTestStatus = null;

    public function testFonnte() {
        if (empty(trim($this->fonnteToken))) {
            $this->fonnteTestStatus = 'error';
            \Flux::toast('Fonnte API Token belum diisi!', variant: 'danger');
            return;
        }

        try {
            $res = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
                'Authorization' => trim($this->fonnteToken)
            ])->post('https://api.fonnte.com/device');

            if ($res->successful() && $res->json('status')) {
                $this->fonnteTestStatus = 'success';
                $device = $res->json('name') ?? ($res->json('device') ?? 'Terkoneksi');
                $quota = $res->json('quota') ?? '-';
                \Flux::toast("Koneksi Fonnte WA Berhasil! Device: {$device}, Quota: {$quota}", variant: 'success');
            } else {
                $err = $res->json('reason') ?? 'Token Fonnte tidak valid atau Device Disconnected';
                $this->fonnteTestStatus = 'error';
                \Flux::toast("Koneksi Fonnte Gagal: {$err}", variant: 'danger');
            }
        } catch (\Exception $e) {
            $this->fonnteTestStatus = 'error';
            \Flux::toast('Fonnte Test Error: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function sendTestWaReport() {
        try {
            \Illuminate\Support\Facades\Artisan::call('app:send-executive-report');
            \Flux::toast('Laporan Eksekutif WA Berhasil Dikirim ke Nomor WhatsApp!', variant: 'success');
        } catch (\Exception $e) {
            \Flux::toast('Gagal mengirim Laporan WA: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function mount() {
        $this->clarityId = Setting::where('key', 'clarity_id')->value('value') ?? '';
        $this->beamsInstanceId = Setting::where('key', 'beams_instance_id')->value('value') ?? '';
        $this->beamsSecretKey = Setting::where('key', 'beams_secret_key')->value('value') ?? '';
        $this->pusherAppId = Setting::where('key', 'pusher_app_id')->value('value') ?? '';
        $this->pusherKey = Setting::where('key', 'pusher_key')->value('value') ?? '';
        $this->pusherSecret = Setting::where('key', 'pusher_secret')->value('value') ?? '';
        $this->pusherCluster = Setting::where('key', 'pusher_cluster')->value('value') ?? '';
        $this->fonnteToken = Setting::where('key', 'fonnte_token')->value('value') ?? '';
        $this->aiAssistantName = Setting::where('key', 'ai_assistant_name')->value('value') ?? 'ROMLAH Asisten';
        $this->aiCustomInstruction = Setting::where('key', 'ai_custom_instruction')->value('value') ?? '';

        $this->waReportEnabled = Setting::where('key', 'wa_report_enabled')->value('value') === 'true';
        $this->waReportFrequency = Setting::where('key', 'wa_report_frequency')->value('value') ?? 'daily';
        
        $timeStr = Setting::where('key', 'wa_report_time')->value('value') ?? '23:59';
        $timeParts = explode(':', $timeStr);
        $this->waReportHour = sprintf('%02d', intval($timeParts[0] ?? 23));
        $this->waReportMinute = sprintf('%02d', intval($timeParts[1] ?? 59));
        
        $this->waReportRecipients = Setting::where('key', 'wa_report_recipients')->value('value') ?? '';
        
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
            'ai_assistant_name' => $this->aiAssistantName ?: 'ROMLAH Asisten',
            'ai_custom_instruction' => $this->aiCustomInstruction,
            'ai_providers' => json_encode($this->aiProviders),
            'wa_report_enabled' => $this->waReportEnabled ? 'true' : 'false',
            'wa_report_frequency' => $this->waReportFrequency,
            'wa_report_time' => sprintf('%02d:%02d', intval($this->waReportHour), intval($this->waReportMinute)),
            'wa_report_recipients' => $this->waReportRecipients,
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

            <!-- Profil & Personalisasi Asisten AI -->
            <div class="bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/80 rounded-2xl p-5 space-y-4 mb-6">
                <div class="flex items-center gap-2">
                    <flux:icon.sparkles class="w-5 h-5 text-indigo-500" />
                    <flux:heading size="lg">Profil & Personalisasi Asisten AI</flux:heading>
                </div>
                <flux:subheading>
                    Sesuaikan nama panggilan dan instruksi khusus (persona) untuk gaya obrolan & fokus analisis AI perusahaan Anda.
                </flux:subheading>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <flux:input 
                            wire:model="aiAssistantName" 
                            label="Nama Asisten AI" 
                            placeholder="Contoh: ROMLAH Asisten" 
                            description="Nama yang tampil pada header widget chat."
                        />
                    </div>
                    <div class="md:col-span-2">
                        <flux:textarea 
                            wire:model="aiCustomInstruction" 
                            label="Instruksi Khusus / Gaya Bahasa AI (Opsional)" 
                            placeholder="Contoh: Kamu adalah asisten resmi PT. Romlah Jaya. Berikan jawaban yang ramah, sopan, dan utamakan analisis stok minimum serta piutang." 
                            description="Petunjuk khusus kepribadian, gaya obrolan, dan fokus analisis AI."
                            rows="2"
                        />
                    </div>
                </div>
            </div>

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

            <div class="mt-2" x-data="{ status: $wire.entangle('fonnteTestStatus') }" x-init="$watch('status', value => { if (value) setTimeout(() => status = null, 4000) })">
                <flux:button wire:click="testFonnte" 
                             x-bind:class="status === 'success' ? '!bg-green-500 !text-white !border-green-500 hover:!bg-green-600' : (status === 'error' ? '!bg-red-500 !text-white !border-red-500 hover:!bg-red-600' : '')"
                             variant="outline" icon="bolt" size="sm" wire:loading.attr="disabled">
                    <span x-text="status === 'success' ? 'Perangkat WA Terkoneksi!' : (status === 'error' ? 'Gagal Terkoneksi!' : 'Test Koneksi Device Fonnte')"></span>
                </flux:button>
            </div>

            <!-- Jadwal Laporan Eksekutif WhatsApp Otomatis -->
            <div class="mt-6 p-5 bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/50 rounded-2xl space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:icon.document-chart-bar class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <div>
                            <h5 class="text-sm font-bold text-emerald-950 dark:text-emerald-200">Laporan Operasional Eksekutif WA Otomatis</h5>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Kirim rangkuman performa seluruh divisi (Keuangan, Sales, Gudang, Produksi) secara otomatis via WhatsApp.</p>
                        </div>
                    </div>
                    <flux:switch wire:model.live="waReportEnabled" label="Aktifkan Laporan WA" />
                </div>

                @if($waReportEnabled)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-emerald-100 dark:border-emerald-900/40">
                        <div>
                            <flux:select wire:model="waReportFrequency" label="Frekuensi Pengiriman">
                                <option value="daily">Harian (Setiap Hari)</option>
                                <option value="weekly">Mingguan (Setiap Minggu)</option>
                                <option value="monthly">Bulanan (Setiap Akhir Bulan)</option>
                            </flux:select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Jam Pengiriman (WIB - 24 Jam)</label>
                            <div class="flex items-center gap-1.5">
                                <div class="flex-1">
                                    <flux:select wire:model.live="waReportHour">
                                        @for($h = 0; $h < 24; $h++)
                                            @php $val = sprintf('%02d', $h); @endphp
                                            <option value="{{ $val }}">Jam {{ $val }}</option>
                                        @endfor
                                    </flux:select>
                                </div>
                                <span class="font-bold text-zinc-500 text-sm">:</span>
                                <div class="flex-1">
                                    <flux:select wire:model.live="waReportMinute">
                                        @for($m = 0; $m < 60; $m += 5)
                                            @php $val = sprintf('%02d', $m); @endphp
                                            <option value="{{ $val }}">{{ $val }} mnt</option>
                                        @endfor
                                        <option value="59">59 mnt</option>
                                    </flux:select>
                                </div>
                            </div>
                        </div>
                        <div>
                            <flux:input 
                                wire:model="waReportRecipients" 
                                label="Nomor WA Penerima" 
                                placeholder="Contoh: 081234567890, 089876543210" 
                                description="Nomor Owner / Super Admin (pisahkan koma)."
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <span class="text-xs text-emerald-700 dark:text-emerald-400 font-medium">💡 Jadwal aktif di server: {{ strtoupper($waReportFrequency) }} Pukul {{ sprintf('%02d:%02d', intval($waReportHour), intval($waReportMinute)) }} WIB</span>
                        <flux:button variant="subtle" icon="paper-airplane" size="sm" wire:click="sendTestWaReport" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="sendTestWaReport">Kirim Laporan WA Sekarang (Tes)</span>
                            <span wire:loading wire:target="sendTestWaReport">Sending WA...</span>
                        </flux:button>
                    </div>
                @endif
            </div>
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

