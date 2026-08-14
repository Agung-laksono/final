<?php

use function Livewire\Volt\{state, on};
use Modules\Inventory\Models\Item;
use Illuminate\Support\Facades\Log;

state([
    'items' => [],
    'title' => 'Katalog Promo',
    'valid_until' => '',
    'showModal' => false,
    'generatedUrl' => '',
]);

on([
    'open-generate-catalog-modal' => function (...$args) {
        // Retrieve the selected item IDs from the event payload. 
        Log::info('open-generate-catalog-modal payload', ['args' => $args]);
        
        $items = [];
        if (!empty($args)) {
            // Check if Livewire passed it as a named argument
            if (array_key_exists('data', $args)) {
                $items = $args['data'];
            } 
            // Check if Livewire passed it as the first positional argument
            elseif (array_key_exists(0, $args)) {
                $first = $args[0];
                if (is_array($first) && isset($first['data'])) {
                    $items = $first['data'];
                } elseif (is_array($first)) {
                    $items = $first;
                }
            }
        }
        
        $this->items = $items;
        
        $this->title = 'Katalog Promo';
        $this->valid_until = now()->addDay()->format('Y-m-d\TH:i');
        $this->generatedUrl = '';
        $this->showModal = true;
    }
]);

$generateLink = function () {
    if (empty($this->items)) {
        \Flux\Flux::toast('Pilih minimal 1 barang.', variant: 'danger');
        return;
    }

    $payload = [
        'title' => $this->title,
        'exp' => $this->valid_until,
        'items' => $this->items,
    ];

    $hash = \Illuminate\Support\Str::random(6);
    \Illuminate\Support\Facades\Cache::put('catalog_' . $hash, $payload, \Carbon\Carbon::parse($this->valid_until));
    
    // Asumsikan rute /c/{hash}
    $this->generatedUrl = url('/c/' . $hash);
};

$closeModal = function () {
    $this->showModal = false;
};

?>

<div>
<flux:modal wire:model="showModal" class="md:w-[500px]">
    <div class="p-6">
        <div class="mb-4">
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">Buat Katalog Sales</h2>
        </div>

        <div class="space-y-4 mb-6">
            <div class="bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 p-3 rounded-lg text-sm font-medium flex items-center gap-2 border border-indigo-100 dark:border-indigo-800">
                <flux:icon.information-circle class="w-5 h-5" />
                {{ count($items) }} barang terpilih untuk katalog ini.
            </div>

            <flux:input wire:model="title" label="Judul Katalog" placeholder="Contoh: Promo Spesial Kemerdekaan" />
            
            <flux:input type="datetime-local" wire:model="valid_until" label="Berlaku Hingga" />
            
            <div class="text-xs text-zinc-500 mt-1 flex items-center gap-1">
                <flux:icon.clock class="w-4 h-4" /> Default: 1 hari dari sekarang.
            </div>
        </div>

        @if($generatedUrl)
            <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-4 mb-4">
                <label class="block text-xs font-bold text-emerald-700 dark:text-emerald-400 mb-2 uppercase tracking-wider">Link Publik Berhasil Dibuat!</label>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $generatedUrl }}" class="flex-1 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-600 dark:text-zinc-300 w-full outline-none">
                    
                    <flux:button variant="primary" 
                        x-data="{ copied: false }" 
                        title="Copy Link"
                        @click="
                            navigator.clipboard.writeText('{{ $generatedUrl }}'); 
                            copied = true; 
                            setTimeout(() => copied = false, 2000);
                        "
                    >
                        <span x-show="!copied"><flux:icon.clipboard-document class="w-4 h-4" /></span>
                        <span x-show="copied" x-cloak><flux:icon.check class="w-4 h-4" /></span>
                    </flux:button>
                    
                    <flux:button variant="filled" 
                        @click="window.open('{{ $generatedUrl }}', '_blank')" 
                        title="Buka di tab baru">
                        <flux:icon.arrow-top-right-on-square class="w-4 h-4" />
                    </flux:button>
                </div>
                <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-500">
                    Kirim link ini ke pelanggan. Pelanggan bisa membukanya dari HP.
                </div>
            </div>
        @else
            <div class="flex flex-col-reverse sm:flex-row mt-6 gap-2 sm:gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button wire:click="closeModal" variant="subtle" class="w-full sm:w-auto">Batal</flux:button>
                
                <flux:spacer class="hidden sm:block" />
                
                <flux:button variant="subtle" id="btn-download-img" icon="photo" onclick="downloadCatalogImage()" class="w-full sm:w-auto">
                    Download Gambar
                </flux:button>
                <flux:button wire:click="generateLink" variant="primary" icon="link" class="w-full sm:w-auto">
                    Buat Link
                </flux:button>
            </div>
        @endif
</flux:modal>

    {{-- Hidden element to be captured by html2canvas (dipindah ke luar modal agar tidak terkena efek CSS modal) --}}
    @if(count($items) > 0)
        @php
            // Fetch models for rendering
            $captureItems = Item::whereIn('id', $items)->get();
        @endphp
        <div class="fixed inset-0 z-[-9999] opacity-0 pointer-events-none overflow-hidden flex items-start justify-center">
            <div id="catalog-capture-area" class="bg-white w-[600px] shrink-0 p-6 text-zinc-900" style="font-family: sans-serif;">
                <div class="text-center mb-6 border-b border-zinc-200 pb-4">
                    <h1 class="text-3xl font-bold text-indigo-700">{{ $title ?: 'Katalog Promo' }}</h1>
                    <p class="text-base mt-2 text-zinc-600 bg-amber-100 inline-block px-4 py-1 rounded-full font-semibold border border-amber-200">
                        Berlaku Hingga: {{ \Carbon\Carbon::parse($valid_until ?: now()->addDay())->translatedFormat('d F Y, H:i') }}
                    </p>
                </div>
                
                <div class="flex flex-col gap-6">
                    @foreach($captureItems as $item)
                    <div class="bg-black rounded-2xl overflow-hidden shadow-md relative group">
                        @if ($item->image)
                            <img src="{{ asset('storage/' . $item->image) }}" crossorigin="anonymous" class="w-full h-auto block" style="min-height: 200px; object-fit: cover;">
                        @else
                            <div class="w-full aspect-[4/3] bg-zinc-100 flex items-center justify-center text-zinc-400 p-4">
                                <flux:icon.photo class="w-16 h-16 opacity-20" />
                            </div>
                        @endif
                        
                        @if($item->discount > 0)
                            <div class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-3 py-1.5 rounded shadow-sm z-20">
                                DISKON
                            </div>
                        @endif
                        
                        {{-- Watermark --}}
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-10 overflow-hidden">
                            <div class="transform -rotate-[15deg] border-4 border-white/20 text-white/50 bg-black/10 text-3xl font-black uppercase px-4 py-2 rounded-xl shadow-sm text-center">
                                <span class="block text-lg opacity-80 mb-1">Berlaku Hingga</span>
                                {{ \Carbon\Carbon::parse($valid_until ?: now()->addDay())->translatedFormat('d M Y') }}
                            </div>
                        </div>
                        
                        {{-- Info Overlay (Bottom Gradient) --}}
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent pt-24 pb-5 px-6 z-20 flex flex-col justify-end">
                            <div class="text-sm text-zinc-300 mb-1.5 flex justify-between items-center" style="text-shadow: 0 1px 2px rgba(0,0,0,0.8);">
                                <span class="truncate pr-2">{{ $item->category?->name ?? 'Kategori' }}</span>
                                <span class="font-mono text-xs opacity-70">{{ $item->sku }}</span>
                            </div>
                            
                            <div class="flex flex-row justify-between items-end gap-2">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold text-xl text-white leading-tight line-clamp-2" style="text-shadow: 0 1px 3px rgba(0,0,0,0.8);">
                                        @if($item->alias)
                                            {{ $item->alias }} <span class="text-xs font-normal text-zinc-300 ml-1">- {{ $item->name }}</span>
                                        @else
                                            {{ $item->name }}
                                        @endif
                                    </h3>
                                </div>
                                
                                <div class="flex flex-col items-end shrink-0" style="text-shadow: 0 1px 3px rgba(0,0,0,0.8);">
                                    @if($item->discount > 0)
                                        <div class="text-sm text-zinc-300 line-through mb-0.5">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</div>
                                        <div class="text-red-400 font-black text-2xl leading-none">Rp {{ number_format($item->selling_price - $item->discount, 0, ',', '.') }}</div>
                                    @else
                                        <div class="text-white font-black text-2xl">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                
                <div class="mt-8 text-center text-sm text-zinc-400 border-t border-zinc-100 pt-6 pb-2">
                    Katalog resmi - Dibuat pada {{ now()->translatedFormat('d F Y') }}
                </div>
            </div>
        </div>
    @endif
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js"></script>
<script>
    function downloadCatalogImage() {
        const captureArea = document.getElementById('catalog-capture-area');
        if (!captureArea) {
            alert('Area katalog tidak ditemukan!');
            return;
        }
        
        if (typeof htmlToImage === 'undefined') {
            alert('Library gambar sedang dimuat, coba lagi dalam 2 detik.');
            return;
        }
        
        const btn = document.getElementById('btn-download-img');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Memproses...';
        btn.disabled = true;

        // Beri jeda sedikit agar semua gambar bisa di-load dengan baik
        setTimeout(() => {
            htmlToImage.toJpeg(captureArea, { 
                quality: 0.95, 
                backgroundColor: '#ffffff',
                pixelRatio: 2, // High resolution
                style: {
                    position: 'relative',
                    top: '0',
                    left: '0',
                    transform: 'none'
                }
            })
            .then(function (dataUrl) {
                const link = document.createElement('a');
                link.download = 'Katalog-' + new Date().getTime() + '.jpg';
                link.href = dataUrl;
                link.click();
                
                btn.innerHTML = originalText;
                btn.disabled = false;
            })
            .catch(function (error) {
                console.error('Error generating image', error);
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Gagal membuat gambar: ' + (error.message || 'Unknown error'));
            });
        }, 500);
    }
</script>
</div>
