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

    public function addAiProvider() {
        $this->aiProviders[] = ['name' => '', 'key' => ''];
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

