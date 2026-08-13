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

mount(function () {
    $data = request()->query('data');
    
    if (!$data) {
        $this->error = 'Link tidak valid atau tidak lengkap.';
        return;
    }

    try {
        // Pulihkan karakter '+' yang mungkin berubah menjadi spasi dari URL lama
        $cleanData = str_replace(' ', '+', $data);
        $decoded = json_decode(base64_decode($cleanData), true);
        
        if (!is_array($decoded) || !isset($decoded['items']) || !isset($decoded['exp'])) {
            $this->error = 'Format link tidak valid.';
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
                <div class="bg-indigo-800/50 rounded-lg px-2.5 py-1.5 flex items-center gap-1.5 border border-indigo-500/30 shrink-0">
                    <flux:icon.clock class="w-3.5 h-3.5 text-indigo-300" />
                    <div class="text-[9px] sm:text-xs text-indigo-100 leading-tight">
                        Berlaku hingga:<br>
                        <span class="font-bold text-white">{{ $validUntil->translatedFormat('d M Y, H:i') }}</span>
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
