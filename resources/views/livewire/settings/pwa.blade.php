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
    public bool $seo_allow_indexing = false;
    public bool $seo_block_ai_crawlers = true;

    public function mount(): void
    {
        $this->app_name              = Setting::where('key', 'pwa_name')->value('value') ?? 'Inventory System';
        $this->app_short_name        = Setting::where('key', 'pwa_short_name')->value('value') ?? 'Inventory';
        $this->app_description       = Setting::where('key', 'pwa_description')->value('value') ?? 'Sistem Manajemen Inventaris';
        $this->theme_color_light     = Setting::where('key', 'pwa_theme_color_light')->value('value') ?? '#ffffff';
        $this->theme_color_dark      = Setting::where('key', 'pwa_theme_color_dark')->value('value') ?? '#18181b';
        $this->currentIcon           = Setting::where('key', 'pwa_icon')->value('value');
        $this->seo_allow_indexing    = (bool) (Setting::where('key', 'seo_allow_indexing')->value('value') ?? false);
        $this->seo_block_ai_crawlers = (bool) (Setting::where('key', 'seo_block_ai_crawlers')->value('value') ?? true);
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
        Setting::updateOrCreate(['key' => 'seo_allow_indexing'],    ['value' => $this->seo_allow_indexing ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'seo_block_ai_crawlers'], ['value' => $this->seo_block_ai_crawlers ? '1' : '0']);

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

            // Timpa icon bawaan sistem agar notifikasi & favicon ikut terganti
            if (file_exists(public_path('apple-touch-icon.png'))) {
                file_put_contents(public_path('apple-touch-icon.png'), $imageData);
            }
            if (file_exists(public_path('favicon.ico'))) {
                file_put_contents(public_path('favicon.ico'), $imageData);
            }

            Setting::updateOrCreate(['key' => 'pwa_icon'], ['value' => $filename]);
            $this->currentIcon = $filename;
            $this->app_icon = null;
        }

        \Illuminate\Support\Facades\Cache::forget('setting_pwa_theme_colors');
        \Illuminate\Support\Facades\Cache::forget('setting_pwa_icon');
        \Illuminate\Support\Facades\Cache::forget('setting_pwa_name');
        \Illuminate\Support\Facades\Cache::forget('setting_seo_allow_indexing');
        \Illuminate\Support\Facades\Cache::forget('setting_seo_block_ai_crawlers');

        $this->syncRobotsTxt();

        \Flux::toast('Pengaturan aplikasi & SEO berhasil disimpan.', variant: 'success');
    }

    public function syncRobotsTxt(): void
    {
        $content = self::generateRobotsTxt((bool)$this->seo_allow_indexing, (bool)$this->seo_block_ai_crawlers);
        try {
            file_put_contents(public_path('robots.txt'), $content);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mengupdate robots.txt: ' . $e->getMessage());
        }
    }

    public static function generateRobotsTxt(bool $allowIndexing, bool $blockAi): string
    {
        if (!$allowIndexing) {
            return "# Robots.txt - Dibuat otomatis oleh Sistem\n" .
                   "# Seluruh bot & mesin pencari dilarang menelusuri aplikasi\n" .
                   "User-agent: *\n" .
                   "Disallow: /\n";
        }

        if ($blockAi) {
            return "# Robots.txt - Dibuat otomatis oleh Sistem\n" .
                   "# Mesin pencari utama diizinkan (Google, Bing, Apple)\n" .
                   "User-agent: Googlebot\n" .
                   "Allow: /\n\n" .
                   "User-agent: Bingbot\n" .
                   "Allow: /\n\n" .
                   "User-agent: Applebot\n" .
                   "Allow: /\n\n" .
                   "# Blokir bot AI Scraper & Web Crawler LLM\n" .
                   "User-agent: GPTBot\n" .
                   "Disallow: /\n\n" .
                   "User-agent: ChatGPT-User\n" .
                   "Disallow: /\n\n" .
                   "User-agent: ClaudeBot\n" .
                   "Disallow: /\n\n" .
                   "User-agent: anthropic-ai\n" .
                   "Disallow: /\n\n" .
                   "User-agent: Claude-Web\n" .
                   "Disallow: /\n\n" .
                   "User-agent: PerplexityBot\n" .
                   "Disallow: /\n\n" .
                   "User-agent: CCBot\n" .
                   "Disallow: /\n\n" .
                   "User-agent: Bytespider\n" .
                   "Disallow: /\n\n" .
                   "User-agent: Google-Extended\n" .
                   "Disallow: /\n\n" .
                   "User-agent: FacebookBot\n" .
                   "Disallow: /\n\n" .
                   "User-agent: Diffbot\n" .
                   "Disallow: /\n\n" .
                   "User-agent: Omgilibot\n" .
                   "Disallow: /\n\n" .
                   "# Izinkan bot publik lainnya\n" .
                   "User-agent: *\n" .
                   "Allow: /\n";
        }

        return "# Robots.txt - Dibuat otomatis oleh Sistem\n" .
               "User-agent: *\n" .
               "Disallow:\n";
    }
};
?>

<x-pages::settings.layout
    heading="Pengaturan Aplikasi & SEO"
    subheading="Atur nama, ikon tampilan aplikasi (PWA), serta kontrol izin perayapan (crawling) oleh Google dan bot AI.">

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

        {{-- Visibilitas Mesin Pencari & Bot AI (SEO & Crawling) --}}
        <div class="bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/80 rounded-2xl p-5 space-y-5">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/50 rounded-xl text-indigo-600 dark:text-indigo-400">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                    </svg>
                </div>
                <div class="flex-1">
                    <flux:heading size="lg">Izin Perayapan Web & Bot AI (Crawling & Indexing)</flux:heading>
                    <flux:subheading>
                        Kendalikan apakah Google, mesin pencari lain, serta bot scraper AI (seperti GPTBot, Claude, dll.) diizinkan membaca halaman publik website Anda.
                    </flux:subheading>
                </div>
            </div>

            <div class="space-y-4 pt-1">
                {{-- Toggle 1: Izinkan Google / Search Engine --}}
                <div class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/80 rounded-xl space-y-1">
                    <flux:switch
                        wire:model.live="seo_allow_indexing"
                        label="Izinkan Mesin Pencari (Google, Bing, dll.)"
                        description="Aktifkan agar halaman publik aplikasi ini dapat ditemukan di Google dan mesin pencari lainnya. Nonaktifkan jika aplikasi ini adalah sistem internal tertutup."
                    />
                </div>

                {{-- Toggle 2: Blokir Bot AI (hanya relevan jika indexing aktif) --}}
                @if($seo_allow_indexing)
                    <div class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/80 rounded-xl space-y-1">
                        <flux:switch
                            wire:model="seo_block_ai_crawlers"
                            label="Blokir Bot AI (GPTBot, ClaudeBot, Perplexity, CCBot)"
                            description="Tetap izinkan Google/Bing untuk pencarian, tetapi tolak bot AI agar tidak men-scrape data dan konten aplikasi untuk pelatihan model AI."
                        />
                    </div>
                @else
                    <div class="flex items-start gap-2.5 text-xs text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 p-3.5 rounded-xl">
                        <flux:icon.information-circle class="w-4 h-4 shrink-0 text-amber-500 mt-0.5" />
                        <div class="leading-relaxed">
                            <strong>Mode Privat Aktif:</strong> Seluruh bot dan mesin pencari (termasuk Googlebot dan bot AI) akan diblokir total dari menelusuri atau mengindeks aplikasi ini (<code>Disallow: /</code> dan <code>noindex, nofollow</code>).
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400 px-1 pt-1">
                    <span>File <code>public/robots.txt</code> akan diperbarui secara otomatis saat tombol simpan ditekan.</span>
                    <a href="/robots.txt" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium inline-flex items-center gap-1">
                        Lihat isi robots.txt
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    </a>
                </div>
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
