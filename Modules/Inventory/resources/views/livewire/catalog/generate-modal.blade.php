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

    $encoded = urlencode(base64_encode(json_encode($payload)));
    
    // Asumsikan rute /catalog
    $this->generatedUrl = url('/catalog?data=' . $encoded);
};

$closeModal = function () {
    $this->showModal = false;
};

?>

<div>
<flux:modal wire:model="showModal" class="md:w-[500px]">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">Buat Katalog Sales</h2>
            <button wire:click="closeModal" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                <flux:icon.x-mark class="w-5 h-5" />
            </button>
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
                        @click="
                            navigator.clipboard.writeText('{{ $generatedUrl }}'); 
                            copied = true; 
                            setTimeout(() => copied = false, 2000);
                        "
                    >
                        <span x-show="!copied"><flux:icon.clipboard-document class="w-4 h-4" /></span>
                        <span x-show="copied" x-cloak><flux:icon.check class="w-4 h-4" /></span>
                    </flux:button>
                </div>
                <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-500">
                    Kirim link ini ke pelanggan. Pelanggan bisa membukanya dari HP.
                </div>
            </div>
        @else
            <div class="flex items-center gap-3 justify-end mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button wire:click="closeModal" variant="subtle">Batal</flux:button>
                
                <flux:button variant="subtle" id="btn-download-img" icon="photo" onclick="downloadCatalogImage()">
                    Download Gambar
                </flux:button>
                <flux:button wire:click="generateLink" variant="primary" icon="link">
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
        <div id="catalog-capture-area" class="fixed top-[-9999px] left-[-9999px] bg-white w-[600px] p-6 text-zinc-900 z-[-1]" style="font-family: sans-serif;">
            <div class="text-center mb-6 border-b border-zinc-200 pb-4">
                <h1 class="text-3xl font-bold text-indigo-700">{{ $title ?: 'Katalog Promo' }}</h1>
                <p class="text-base mt-2 text-zinc-600 bg-amber-100 inline-block px-4 py-1 rounded-full font-semibold border border-amber-200">
                    Berlaku Hingga: {{ \Carbon\Carbon::parse($valid_until ?: now()->addDay())->translatedFormat('d F Y, H:i') }}
                </p>
            </div>
            
            <div class="flex flex-col gap-6">
                @foreach($captureItems as $item)
                <div class="border border-zinc-200 rounded-2xl overflow-hidden bg-white shadow-sm flex flex-row">
                    <div class="w-[200px] shrink-0 relative bg-zinc-50 flex items-center justify-center border-r border-zinc-100">
                        @if($item->image)
                            <img src="{{ asset('storage/' . $item->image) }}" class="w-full h-full object-contain p-2">
                        @else
                            <div class="w-full aspect-square flex items-center justify-center text-zinc-300">
                                <flux:icon.photo class="w-12 h-12 opacity-20" />
                            </div>
                        @endif
                        
                        @if($item->discount > 0)
                            <div class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-md shadow-sm">
                                DISKON
                            </div>
                        @endif
                    </div>
                    <div class="p-5 flex flex-col flex-1 justify-center">
                        <div class="text-sm text-zinc-500 mb-1 flex justify-between items-center">
                            <span>{{ $item->category?->name ?? 'Kategori' }}</span>
                            <span class="font-mono text-xs">{{ $item->sku }}</span>
                        </div>
                        <h3 class="font-bold text-xl leading-tight mb-3 text-zinc-900">
                            @if($item->alias)
                                {{ $item->alias }} <span class="text-xs font-normal text-zinc-500 ml-1">- {{ $item->name }}</span>
                            @else
                                {{ $item->name }}
                            @endif
                        </h3>
                        
                        <div class="mt-auto border-t border-zinc-100 pt-3">
                            <span class="text-xs text-zinc-500 block mb-1">Harga Spesial</span>
                            @if($item->discount > 0)
                                <div class="flex items-center gap-3">
                                    <div class="text-red-600 font-bold text-2xl">Rp {{ number_format($item->selling_price - $item->discount, 0, ',', '.') }}</div>
                                    <div class="text-sm text-zinc-400 line-through">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</div>
                                </div>
                            @else
                                <div class="font-bold text-2xl text-zinc-900">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            
            <div class="mt-8 text-center text-sm text-zinc-400 border-t border-zinc-100 pt-6 pb-2">
                Katalog resmi - Dibuat pada {{ now()->translatedFormat('d F Y') }}
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
