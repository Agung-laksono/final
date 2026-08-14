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

    {{-- Hidden elements to be captured by html2canvas (dipindah ke luar modal agar tidak terkena efek CSS modal) --}}
    @if(count($items) > 0)
        @php
            // Fetch models for rendering
            $captureItems = Item::whereIn('id', $items)->get();
        @endphp
        <div class="fixed inset-0 z-[-9999] opacity-0 pointer-events-none overflow-hidden flex items-start justify-center flex-wrap gap-4">
            @foreach($captureItems as $index => $item)
            <div id="catalog-capture-{{ $index }}" class="catalog-capture-node bg-black w-[1080px] shrink-0 relative overflow-hidden" style="font-family: sans-serif;">
                {{-- Background Image (Provides Height) --}}
                @if($item->image)
                    <img src="{{ asset('storage/' . $item->image) }}" crossorigin="anonymous" class="w-full h-auto block min-h-[600px] object-cover">
                @else
                    <div class="w-full aspect-[4/3] bg-zinc-900 flex items-center justify-center">
                        <flux:icon.photo class="w-48 h-48 text-zinc-800" />
                    </div>
                @endif
                
                {{-- Top Header (Overlay) --}}
                <div class="absolute top-0 inset-x-0 z-20 bg-gradient-to-b from-black/60 to-transparent pt-12 pb-12 px-12 flex justify-between items-start pointer-events-none">
                    <div>
                        <h1 class="text-5xl font-black text-white mb-3 tracking-tight drop-shadow-lg">{{ $title ?: 'Katalog Promo' }}</h1>
                        <div class="inline-flex items-center gap-2 bg-indigo-600/90 backdrop-blur-sm px-4 py-1.5 rounded-full border border-indigo-400/50">
                            <flux:icon.clock class="w-5 h-5 text-indigo-100" />
                            <p class="text-xl text-white font-bold tracking-wide">Berlaku Hingga: {{ \Carbon\Carbon::parse($valid_until ?: now()->addDay())->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                    </div>
                    @if($item->discount > 0)
                    <div class="bg-red-500 text-white font-black text-3xl px-6 py-3 rounded-2xl shadow-2xl transform rotate-3 border-4 border-red-400">
                        PROMO SPESIAL
                    </div>
                    @endif
                </div>

                {{-- Bottom Info (Overlay) --}}
                <div class="absolute bottom-0 inset-x-0 z-20 bg-gradient-to-t from-black/70 to-transparent pt-24 pb-12 px-12 flex justify-between items-end pointer-events-none">
                    <div class="flex-1 pr-10">
                        <div class="flex items-center gap-4 mb-3">
                            <span class="bg-zinc-800 text-zinc-200 text-xl px-4 py-1.5 rounded-lg border border-zinc-700 font-bold tracking-wider uppercase">{{ $item->category?->name ?? 'Kategori' }}</span>
                            <span class="font-mono text-xl text-zinc-400">{{ $item->sku }}</span>
                        </div>
                        <h2 class="text-[3.5rem] font-black text-white leading-[1.1] drop-shadow-2xl">
                            @if($item->alias)
                                {{ $item->alias }}
                            @else
                                {{ $item->name }}
                            @endif
                        </h2>
                        @if($item->alias)
                            <p class="text-3xl text-zinc-300 mt-2">{{ $item->name }}</p>
                        @endif
                    </div>
                    
                    <div class="text-right shrink-0">
                        <p class="text-2xl text-zinc-400 mb-2 font-medium">Harga Spesial</p>
                        @if($item->discount > 0)
                            <p class="text-4xl text-zinc-400 line-through mb-2 font-bold decoration-red-500/50 decoration-4">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</p>
                            <p class="text-[4.5rem] font-black text-emerald-400 drop-shadow-2xl leading-none">Rp {{ number_format($item->selling_price - $item->discount, 0, ',', '.') }}</p>
                        @else
                            <p class="text-[4.5rem] font-black text-white drop-shadow-2xl leading-none">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js"></script>
<script>
    async function downloadCatalogImage() {
        const nodes = document.querySelectorAll('.catalog-capture-node');
        if (nodes.length === 0) {
            alert('Area katalog tidak ditemukan!');
            return;
        }
        
        if (typeof htmlToImage === 'undefined') {
            alert('Library gambar sedang dimuat, coba lagi dalam 2 detik.');
            return;
        }
        
        const btn = document.getElementById('btn-download-img');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Memproses ' + nodes.length + ' Gambar...';
        btn.disabled = true;

        try {
            // Beri jeda agar resource (gambar) sempat di-load
            await new Promise(r => setTimeout(r, 500));

            for (let i = 0; i < nodes.length; i++) {
                btn.innerHTML = `Mengunduh (${i + 1}/${nodes.length})...`;
                
                const dataUrl = await htmlToImage.toJpeg(nodes[i], { 
                    quality: 0.95, 
                    backgroundColor: '#000000',
                    pixelRatio: 1, // 1080x1080 is already large enough
                    style: {
                        transform: 'none'
                    }
                });
                
                const link = document.createElement('a');
                link.download = `Promo-${new Date().getTime()}-Barang-${i+1}.jpg`;
                link.href = dataUrl;
                link.click();
                
                // Jeda 500ms antar unduhan agar browser tidak memblokir multiple downloads
                await new Promise(r => setTimeout(r, 500));
            }
            
            btn.innerHTML = originalText;
            btn.disabled = false;
        } catch (error) {
            console.error('Error generating image', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('Gagal membuat gambar: ' + (error.message || 'Unknown error'));
        }
    }
</script>
</div>
