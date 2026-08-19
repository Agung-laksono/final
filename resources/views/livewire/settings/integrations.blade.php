<?php

use function Livewire\Volt\{state, mount};
use App\Models\Setting;
use App\Events\SettingUpdated;
use Illuminate\Support\Facades\Cache;

state([
    'clarityId' => '',
    'beamsInstanceId' => '',
    'beamsSecretKey' => '',
    'pusherAppId' => '',
    'pusherKey' => '',
    'pusherSecret' => '',
    'pusherCluster' => '',
    'fonnteToken' => '',
    'aiProviders' => [],
    'gudangHandlesShipping' => false,
    'enableAiChat' => false,
]);

mount(function () {
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
});

$addAiProvider = function () {
    $this->aiProviders[] = ['name' => '', 'key' => ''];
};

$removeAiProvider = function ($index) {
    unset($this->aiProviders[$index]);
    $this->aiProviders = array_values($this->aiProviders); // Re-index array
};

$save = function () {
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

    // Cache agar tidak query DB terus (1 jam)
    Cache::put('setting_gudang_handles_shipping', $shippingValue, now()->addHour());
    Cache::put('setting_enable_ai_chat', $enableAiChatValue, now()->addHour());
    Cache::forget('setting_clarity_id');
    Cache::forget('app_integration_settings');

    // Broadcast ke semua client yang sedang membuka halaman Kanban
    SettingUpdated::safeDispatch('gudang_handles_shipping', $shippingValue);
    SettingUpdated::safeDispatch('enable_ai_chat', $enableAiChatValue);

    \Flux::toast('Pengaturan berhasil disimpan!');
};

$testPusherBeams = function () {
    if (empty($this->beamsInstanceId) || empty($this->beamsSecretKey)) {
        \Flux::toast('Kredensial Pusher Beams belum lengkap!', variant: 'danger');
        return;
    }
    
    try {
        if (!class_exists(\Pusher\PushNotifications\PushNotifications::class)) {
            \Flux::toast('Package pusher/pusher-push-notifications belum diinstall.', variant: 'danger');
            return;
        }
        
        $beamsClient = new \Pusher\PushNotifications\PushNotifications([
            "instanceId" => $this->beamsInstanceId,
            "secretKey" => $this->beamsSecretKey,
        ]);
        
        $beamsClient->publishToInterests(
            array("debug-test"),
            array("web" => array("notification" => array(
                "title" => "Test Koneksi",
                "body" => "Berhasil!"
            )))
        );
        \Flux::toast('Koneksi Beams BERHASIL!', variant: 'success');
    } catch (\Exception $e) {
        \Flux::toast('Koneksi Beams GAGAL: ' . $e->getMessage(), variant: 'danger');
    }
};

$testPusherChannels = function () {
    if (empty($this->pusherAppId) || empty($this->pusherKey) || empty($this->pusherSecret) || empty($this->pusherCluster)) {
        \Flux::toast('Kredensial Pusher Channels belum lengkap!', variant: 'danger');
        return;
    }

    try {
        if (!class_exists(\Pusher\Pusher::class)) {
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
        \Flux::toast('Koneksi Channels BERHASIL!', variant: 'success');
    } catch (\Exception $e) {
        \Flux::toast('Koneksi Channels GAGAL: ' . $e->getMessage(), variant: 'danger');
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
                Tambahkan atau kurangi provider AI sesuai kebutuhan Anda. Anda bisa memilih model mana yang akan digunakan langsung dari antarmuka Chat AI nantinya.
            </flux:subheading>

            <flux:switch wire:model="enableAiChat" label="Tampilkan Chat AI" description="Tampilkan atau sembunyikan fitur asisten Chat AI di antarmuka aplikasi." class="mb-4" />

            <div class="space-y-4">
                @foreach($aiProviders as $index => $provider)
                    <div class="flex items-start gap-4">
                        <div class="flex-1">
                            <flux:input wire:model="aiProviders.{{ $index }}.name" placeholder="Nama Provider (Misal: OpenAI)" />
                        </div>
                        <div class="flex-1">
                            <flux:input wire:model="aiProviders.{{ $index }}.key" type="password" placeholder="API Key" />
                        </div>
                        <flux:button variant="danger" icon="trash" wire:click="removeAiProvider({{ $index }})" class="mt-1" />
                    </div>
                @endforeach
            </div>

            <flux:button variant="subtle" icon="plus" wire:click="addAiProvider">Tambah Provider AI</flux:button>
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
            <div class="mt-2">
                <flux:button wire:click="testPusherBeams" variant="outline" icon="bolt" size="sm">Test Koneksi Beams</flux:button>
            </div>
        </div>

        <flux:separator />

        <!-- Pusher (Websockets) -->
        <div class="space-y-4">
            <flux:heading size="lg">Pusher (Websockets)</flux:heading>
            <flux:subheading>
                Pengaturan kredensial Pusher untuk fitur Realtime (Kanban, Notifikasi, Chat).
            </flux:subheading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="pusherAppId" label="App ID" placeholder="Misal: 1234567" />
                <flux:input wire:model="pusherKey" label="App Key" placeholder="Misal: a1b2c3d4e5" />
                <flux:input wire:model="pusherSecret" type="password" label="App Secret" placeholder="Misal: f6g7h8i9j0" />
                <flux:input wire:model="pusherCluster" label="Cluster" placeholder="Misal: ap1" />
            </div>
            <div class="mt-2">
                <flux:button wire:click="testPusherChannels" variant="outline" icon="bolt" size="sm">Test Koneksi Channels</flux:button>
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

