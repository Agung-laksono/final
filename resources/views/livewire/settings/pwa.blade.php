<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public string $app_name = '';
    public string $app_short_name = '';
    public string $app_description = '';
    public string $theme_color_light = '#ffffff';
    public string $theme_color_dark = '#18181b';
    public $app_icon = null;
    public ?string $currentIcon = null;

    public function mount(): void
    {
        $this->app_name          = Setting::where('key', 'pwa_name')->value('value') ?? 'Inventory System';
        $this->app_short_name    = Setting::where('key', 'pwa_short_name')->value('value') ?? 'Inventory';
        $this->app_description   = Setting::where('key', 'pwa_description')->value('value') ?? 'Sistem Manajemen Inventaris';
        $this->theme_color_light = Setting::where('key', 'pwa_theme_color_light')->value('value') ?? '#ffffff';
        $this->theme_color_dark  = Setting::where('key', 'pwa_theme_color_dark')->value('value') ?? '#18181b';
        $this->currentIcon       = Setting::where('key', 'pwa_icon')->value('value');
    }

    public function save(): void
    {
        $this->validate([
            'app_name'          => 'required|string|max:50',
            'app_short_name'    => 'required|string|max:20',
            'app_description'   => 'nullable|string|max:200',
            'theme_color_light' => 'required|string|max:20',
            'theme_color_dark'  => 'required|string|max:20',
            'app_icon'          => 'nullable',
        ]);

        Setting::updateOrCreate(['key' => 'pwa_name'],              ['value' => $this->app_name]);
        Setting::updateOrCreate(['key' => 'pwa_short_name'],        ['value' => $this->app_short_name]);
        Setting::updateOrCreate(['key' => 'pwa_description'],       ['value' => $this->app_description]);
        Setting::updateOrCreate(['key' => 'pwa_theme_color_light'], ['value' => $this->theme_color_light]);
        Setting::updateOrCreate(['key' => 'pwa_theme_color_dark'],  ['value' => $this->theme_color_dark]);

        // Handle icon upload (base64 webp from image-cropper)
        if ($this->app_icon && is_string($this->app_icon) && str_starts_with($this->app_icon, 'data:image')) {
            // Delete old icon if exists
            if ($this->currentIcon) {
                Storage::disk('public')->delete($this->currentIcon);
            }

            $base64Image = substr($this->app_icon, strpos($this->app_icon, ',') + 1);
            $imageData   = base64_decode($base64Image);
            $filename    = 'pwa/icon_' . uniqid() . '.webp';
            Storage::disk('public')->put($filename, $imageData);

            Setting::updateOrCreate(['key' => 'pwa_icon'], ['value' => $filename]);
            $this->currentIcon = $filename;
            $this->app_icon = null;
        }

        \Illuminate\Support\Facades\Cache::forget('setting_pwa_theme_colors');

        \Flux::toast('Pengaturan PWA berhasil disimpan.', variant: 'success');
    }
};
?>

<x-pages::settings.layout
    heading="Pengaturan Aplikasi (PWA)"
    subheading="Atur nama, ikon, dan tampilan aplikasi saat diinstal di perangkat pengguna.">

    <form wire:submit="save" class="space-y-8">
        
        {{-- Ikon Aplikasi --}}
        <div class="flex flex-col sm:flex-row gap-6 items-start">
            <div class="w-full sm:w-48 shrink-0">
                <flux:label class="mb-2">Ikon Aplikasi</flux:label>
                <x-image-cropper
                    id="pwa-icon-cropper"
                    wire:model="app_icon"
                    :image="$currentIcon"
                    accept="image/*"
                />
                <p class="text-[11px] text-zinc-400 mt-2 text-center">Digunakan sebagai ikon saat aplikasi dipasang di HP</p>
            </div>

            <div class="flex-1 space-y-4">
                {{-- Nama Aplikasi --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <flux:input
                            wire:model="app_name"
                            label="Nama Aplikasi"
                            placeholder="Contoh: Sistem Inventaris Toko"
                            required
                            maxlength="50"
                        />
                        <p class="text-[11px] text-zinc-400 mt-1">Ditampilkan saat proses instalasi</p>
                    </div>
                    <div>
                        <flux:input
                            wire:model="app_short_name"
                            label="Nama Singkat"
                            placeholder="Contoh: InvToko"
                            required
                            maxlength="20"
                        />
                        <p class="text-[11px] text-zinc-400 mt-1">Nama di layar homescreen HP (maks. 20 karakter)</p>
                    </div>
                </div>

                <flux:textarea
                    wire:model="app_description"
                    label="Deskripsi Aplikasi"
                    placeholder="Deskripsikan fungsi utama aplikasi ini..."
                    rows="3"
                    maxlength="200"
                />

                {{-- Warna Tema --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <flux:label class="mb-2">Warna Bar (Tema Terang)</flux:label>
                        <div class="flex items-center gap-3">
                            <input
                                type="color"
                                wire:model.live="theme_color_light"
                                class="h-10 w-16 rounded cursor-pointer border border-zinc-300 dark:border-zinc-600 bg-transparent p-0.5"
                            />
                            <flux:input
                                wire:model="theme_color_light"
                                placeholder="#ffffff"
                                class="w-full font-mono"
                            />
                        </div>
                    </div>
                    <div>
                        <flux:label class="mb-2">Warna Bar (Tema Gelap)</flux:label>
                        <div class="flex items-center gap-3">
                            <input
                                type="color"
                                wire:model.live="theme_color_dark"
                                class="h-10 w-16 rounded cursor-pointer border border-zinc-300 dark:border-zinc-600 bg-transparent p-0.5"
                            />
                            <flux:input
                                wire:model="theme_color_dark"
                                placeholder="#18181b"
                                class="w-full font-mono"
                            />
                        </div>
                    </div>
                </div>
                <p class="text-[11px] text-zinc-400 mt-2">Menyesuaikan dengan pengaturan dark mode pada sistem operasi HP.</p>
            </div>
        </div>

        {{-- Info Box --}}
        <div class="flex gap-3 bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 rounded-xl p-4">
            <flux:icon.information-circle class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
            <div class="text-sm text-blue-700 dark:text-blue-300">
                <p class="font-semibold mb-1">Cara kerja pengaturan ini</p>
                <p class="text-xs leading-relaxed opacity-80">Setelah menyimpan, buka browser di HP dan pilih <strong>"Tambahkan ke Layar Utama"</strong> atau <strong>"Install App"</strong>. Nama dan ikon yang baru akan langsung diterapkan. Jika sudah pernah diinstal, hapus dan install ulang aplikasi untuk memperbarui ikon.</p>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" icon="check">
                Simpan Pengaturan
            </flux:button>
        </div>
    </form>

</x-pages::settings.layout>
