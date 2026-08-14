<?php

use function Livewire\Volt\{state, mount, layout};
use Modules\Inventory\Models\Item;
use Carbon\Carbon;

layout('layouts.empty'); // Use an empty layout since this is public

state([
    'title' => 'Katalog Promo',
    'validUntil' => null,
    'isExpired' => false,
    'items' => [],
    'error' => null,
]);

mount(function ($hash = null) {
    if (!$hash) {
        $this->error = 'Link tidak valid atau tidak lengkap.';
        return;
    }

    try {
        $decoded = \Illuminate\Support\Facades\Cache::get('catalog_' . $hash);
        
        if (!is_array($decoded) || !isset($decoded['items']) || !isset($decoded['exp'])) {
            $this->error = 'Katalog tidak ditemukan atau sudah kadaluarsa.';
            return;
        }

        $this->title = $decoded['title'] ?? 'Katalog Promo';
        $this->validUntil = Carbon::parse($decoded['exp']);
        
        if (now()->isAfter($this->validUntil)) {
            $this->isExpired = true;
            return;
        }

        // Fetch items and their latest prices
        $this->items = Item::whereIn('id', $decoded['items'])
            ->with(['category', 'type', 'customVariants']) // preload necessary relations (removed non-existent 'images')
            ->get();
            
    } catch (\Exception $e) {
        $this->error = 'Error sistem: ' . $e->getMessage() . ' (Data asli: ' . substr($data, 0, 20) . '...)';
    }
});

?>

<x-slot name="title">{{ $title ?? 'Katalog Promo' }}</x-slot>
<x-slot name="meta">
    <meta property="og:title" content="{{ $title ?? 'Katalog Promo' }}" />
    <meta property="og:description" content="Katalog resmi berlaku hingga {{ $validUntil ? $validUntil->translatedFormat('d M Y, H:i') : '' }}" />
    <meta property="og:type" content="website" />
    @if(isset($items) && count($items) > 0)
        @php $firstImg = $items->where('image', '!=', null)->first(); @endphp
        @if($firstImg)
            <meta property="og:image" content="{{ asset('storage/' . $firstImg->image) }}" />
            <meta property="twitter:card" content="summary_large_image" />
            <meta property="twitter:image" content="{{ asset('storage/' . $firstImg->image) }}" />
        @endif
    @endif
</x-slot>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 pb-20">
    @if($error)
        <div class="flex flex-col items-center justify-center min-h-screen p-6 text-center">
            <div class="w-16 h-16 bg-red-100 dark:bg-red-900/30 text-red-500 rounded-full flex items-center justify-center mb-4">
                <flux:icon.exclamation-triangle class="w-8 h-8" />
            </div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white mb-2">Oops!</h1>
            <p class="text-zinc-500 dark:text-zinc-400">{{ $error }}</p>
        </div>
    @elseif($isExpired)
        <div class="flex flex-col items-center justify-center min-h-screen p-6 text-center">
            <div class="w-16 h-16 bg-amber-100 dark:bg-amber-900/30 text-amber-500 rounded-full flex items-center justify-center mb-4">
                <flux:icon.clock class="w-8 h-8" />
            </div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white mb-2">Promo Telah Berakhir</h1>
            <p class="text-zinc-500 dark:text-zinc-400">
                Maaf, harga khusus pada katalog <strong>{{ $title }}</strong> telah berakhir pada {{ $validUntil->translatedFormat('d F Y H:i') }}.
            </p>
        </div>
    @else
        {{-- Header Promo --}}
        <div class="bg-indigo-600 dark:bg-indigo-900 shadow-sm sticky top-0 z-50">
            <div class="max-w-3xl mx-auto px-4 py-3 sm:py-4 flex flex-row items-center justify-between gap-3">
                <div class="text-left flex-1 min-w-0">
                    <h1 class="text-lg sm:text-2xl font-bold text-white leading-tight truncate">{{ $title }}</h1>
                    <p class="text-indigo-200 text-[10px] sm:text-xs">Katalog Resmi</p>
                </div>
                
                <div class="flex items-center gap-2 shrink-0">
                    @auth
                    {{-- WhatsApp Share / Message Button (Hanya untuk Admin/Sales yang login) --}}
                    <button @click="window.open('https://wa.me/?text={{ urlencode(request()->fullUrl()) }}', '_blank')" 
                       class="bg-emerald-500 hover:bg-emerald-400 text-white rounded-lg px-2.5 py-1.5 flex items-center gap-1.5 shadow-sm transition-colors border border-emerald-400/50">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                        <span class="text-[10px] sm:text-xs font-bold pr-1">Bagikan ke WA</span>
                    </button>
                    @endauth

                    <div class="bg-indigo-800/50 rounded-lg px-2.5 py-1.5 flex items-center gap-1.5 border border-indigo-500/30 shrink-0">
                        <flux:icon.clock class="w-3.5 h-3.5 text-indigo-300" />
                        <div class="text-[9px] sm:text-xs text-indigo-100 leading-tight">
                            Berlaku hingga:<br>
                            <span class="font-bold text-white">{{ $validUntil->translatedFormat('d M Y, H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Catalog Items --}}
        <div class="max-w-3xl mx-auto px-4 py-6">
            <div class="flex flex-col gap-6">
                @foreach($items as $item)
                    <div class="bg-black rounded-2xl overflow-hidden shadow-md relative group">
                        @if ($item->image)
                            <img src="{{ asset('storage/' . $item->image) }}" class="w-full h-auto block" loading="lazy">
                        @else
                            <div class="w-full aspect-[4/3] bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 p-4">
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
                            <div class="transform -rotate-[15deg] border-2 sm:border-4 border-white/20 text-white/40 bg-black/5 text-xl sm:text-3xl font-black uppercase px-4 py-2 rounded-xl backdrop-blur-[1px] shadow-sm text-center mix-blend-overlay">
                                <span class="block text-sm sm:text-lg opacity-80 mb-1">Berlaku</span>
                                {{ $validUntil->translatedFormat('d M Y') }}
                            </div>
                        </div>
                        
                        {{-- Info Overlay (Bottom Gradient) --}}
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent pt-16 pb-4 px-5 z-20 flex flex-col justify-end">
                            <div class="text-xs sm:text-sm text-zinc-300 mb-1.5 flex justify-between items-center drop-shadow-md">
                                <span class="truncate pr-2">{{ $item->category?->name ?? 'Kategori' }}</span>
                                <span class="font-mono text-[10px] sm:text-xs opacity-70">{{ $item->sku }}</span>
                            </div>
                            
                            <div class="flex flex-row justify-between items-end gap-2">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold text-lg sm:text-xl text-white leading-tight line-clamp-2 drop-shadow-lg">
                                        @if($item->alias)
                                            {{ $item->alias }} <span class="text-[11px] sm:text-xs font-normal text-zinc-300 ml-1">- {{ $item->name }}</span>
                                        @else
                                            {{ $item->name }}
                                        @endif
                                    </h3>
                                </div>
                                
                                <div class="flex flex-col items-end shrink-0">
                                    @if($item->discount > 0)
                                        <div class="text-xs sm:text-sm text-zinc-400 line-through mb-0.5">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</div>
                                        <div class="text-red-400 font-black text-xl sm:text-2xl leading-none drop-shadow-lg">Rp {{ number_format($item->selling_price - $item->discount, 0, ',', '.') }}</div>
                                    @else
                                        <div class="text-white font-black text-xl sm:text-2xl drop-shadow-lg">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="mt-8 text-center text-zinc-400 text-xs">
                Katalog ini dibuat otomatis oleh sistem.<br>
                Hubungi staf Sales kami untuk pemesanan.
            </div>
        </div>
    @endif
</div>
