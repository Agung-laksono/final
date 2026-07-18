@props([
    'statusKey'
])

<div class="px-1 mt-2 mb-1">
    <button 
        type="button"
        wire:click="loadMoreColumn('{{ $statusKey }}')" 
        wire:loading.attr="disabled"
        class="w-full text-[11px] font-bold text-zinc-400 hover:text-zinc-700 dark:text-zinc-500 dark:hover:text-zinc-300 py-2 flex items-center justify-center gap-1.5 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-all group disabled:opacity-50"
    >
        <flux:icon.chevron-down wire:loading.remove wire:target="loadMoreColumn('{{ $statusKey }}')" class="w-3 h-3 group-hover:translate-y-px transition-transform duration-300" />
        <flux:icon.arrow-path wire:loading wire:target="loadMoreColumn('{{ $statusKey }}')" class="w-3 h-3 animate-spin" />
        
        <span wire:loading.remove wire:target="loadMoreColumn('{{ $statusKey }}')">Muat Lebih Banyak</span>
        <span wire:loading wire:target="loadMoreColumn('{{ $statusKey }}')">Memuat...</span>
    </button>
</div>
