@props([
    'paginator', 
    'itemName' => 'data', 
    'action' => 'loadMore'
])

@php
    $total = $paginator->total();
    $current = $paginator->count();
    $percentage = $total > 0 ? min(100, round(($current / $total) * 100)) : 0;
@endphp

@if ($total > 0)
    <div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center mt-8 mb-12 w-full max-w-sm mx-auto']) }}>
        
        {{-- Info Teks Kanan-Kiri --}}
        <div class="flex justify-between items-center w-full mb-2.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
            <span>Menampilkan <strong class="text-zinc-900 dark:text-zinc-100">{{ $current }}</strong></span>
            <span>Dari <strong class="text-zinc-900 dark:text-zinc-100">{{ $total }}</strong> {{ $itemName }}</span>
        </div>
        
        {{-- Progress Bar Premium --}}
        <div class="relative w-full h-1.5 bg-zinc-200/60 dark:bg-zinc-800/60 rounded-full overflow-hidden shadow-inner backdrop-blur-sm">
            <div 
                class="absolute top-0 left-0 h-full bg-gradient-to-r from-blue-500 via-indigo-500 to-violet-500 rounded-full transition-all duration-700 ease-in-out" 
                style="width: {{ $percentage }}%"
            >
                {{-- Efek shimmer/kilauan halus di dalam progress bar --}}
                <div class="absolute top-0 right-0 bottom-0 left-0 bg-gradient-to-r from-transparent via-white/20 to-transparent w-full -translate-x-full animate-[shimmer_2s_infinite]"></div>
            </div>
        </div>

        {{-- Tombol Load More yang Dinamis --}}
        @if ($paginator->hasMorePages())
            <div class="mt-6 w-full relative group">
                {{-- Efek Glow di belakang tombol saat dihover --}}
                <div class="absolute -inset-0.5 bg-gradient-to-r from-blue-500 to-violet-500 rounded-xl blur opacity-0 group-hover:opacity-20 transition duration-500"></div>
                
                <button 
                    wire:click="{{ $action }}" 
                    wire:loading.attr="disabled"
                    class="relative w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm font-semibold text-zinc-700 dark:text-zinc-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-all duration-300 shadow-sm hover:shadow-md disabled:opacity-70 disabled:cursor-wait"
                >
                    {{-- Spinner (hanya muncul saat loading) --}}
                    <svg wire:loading wire:target="{{ $action }}" class="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    
                    {{-- Ikon panah (hilang saat loading) --}}
                    <flux:icon.chevron-down wire:loading.remove wire:target="{{ $action }}" class="w-4 h-4 transition-transform group-hover:translate-y-0.5" />

                    <span wire:loading.remove wire:target="{{ $action }}">Muat Lebih Banyak</span>
                    <span wire:loading wire:target="{{ $action }}" class="text-blue-600 dark:text-blue-400">Sedang menarik data...</span>
                </button>
            </div>
        @else
            <div class="mt-6 py-2 px-4 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 rounded-lg text-xs font-medium text-emerald-600 dark:text-emerald-400 flex items-center justify-center gap-2 w-full text-center">
                <flux:icon.check-circle class="w-4 h-4" />
                Seluruh {{ $itemName }} telah ditampilkan
            </div>
        @endif
    </div>

    {{-- Tambahan animasi Shimmer jika belum ada di tailwind config --}}
    <style>
        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }
    </style>
@endif
