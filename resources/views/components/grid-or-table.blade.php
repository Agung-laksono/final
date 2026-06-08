@props([
    'mode', 
])

@php
    $viewMode = $mode
@endphp

{{-- Toggle View --}}
<div class="flex bg-zinc-100 dark:bg-zinc-800 p-1 rounded-lg block md:hidden">
    @if ($viewMode ==='grid')
    <button wire:click="setViewMode('table')" class="p-1.5 rounded-md transition-colors {{ $viewMode === 'table' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}" title="Tampilan Tabel">
        <flux:icon.table-cells class="w-4 h-4" />
    </button>
    @else
    <button wire:click="setViewMode('grid')" class="p-1.5 rounded-md transition-colors {{ $viewMode === 'grid' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}" title="Tampilan Grid">
        <flux:icon.squares-2x2 class="w-4 h-4" />
    </button>
    @endif
</div>
<div class="flex bg-zinc-100 dark:bg-zinc-800 p-1 rounded-lg hidden md:block">
    <button wire:click="setViewMode('table')" class="p-1.5 rounded-md transition-colors {{ $viewMode === 'table' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}" title="Tampilan Tabel">
        <flux:icon.table-cells class="w-4 h-4" />
    </button>
    <button wire:click="setViewMode('grid')" class="p-1.5 rounded-md transition-colors {{ $viewMode === 'grid' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}" title="Tampilan Grid">
        <flux:icon.squares-2x2 class="w-4 h-4" />
    </button>
</div>