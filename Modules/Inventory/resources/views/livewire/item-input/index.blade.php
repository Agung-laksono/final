<?php
use function Livewire\Volt\layout;

layout('layouts::app', ['title' => 'Master Data Inventory']);
?>

<div class="p-0">

    <div class="mx-auto">
        <livewire:item-input.item-list />
        <livewire:item-input.item-form />
        <livewire:item-input.item-detail />
        <livewire:global.item-variants-modal />
        <livewire:catalog.generate-modal />
    </div>


    <!-- Print Label Modal -->
    <livewire:print-label-modal />

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('catalog', {
                selectionMode: false,
                selectedItems: [],
                toggleSelectionMode() {
                    this.selectionMode = !this.selectionMode;
                    if (!this.selectionMode) this.selectedItems = [];
                }
            });
        });
    </script>

    <div x-data x-cloak x-show="$store.catalog.selectionMode" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-10"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-10"
         class="fixed bottom-6 w-[90%] md:w-auto left-1/2 -translate-x-1/2 z-[99999] flex items-center justify-between gap-4 bg-zinc-900 text-white px-4 md:px-6 py-3 md:py-4 rounded-2xl shadow-2xl shadow-zinc-900/40 border-2 border-indigo-500">
        
        <div class="flex items-center gap-2 md:gap-3 border-r border-zinc-700 pr-3 md:pr-4">
            <div class="transition-colors w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center font-bold text-xs md:text-sm"
                 :class="$store.catalog.selectedItems.length > 0 ? 'bg-indigo-500 text-white' : 'bg-zinc-700 text-zinc-400'" 
                 x-text="$store.catalog.selectedItems.length">
            </div>
            <div class="flex flex-col">
                <span class="font-bold text-xs md:text-sm text-indigo-400 leading-tight">Mode Katalog</span>
                <span class="text-[10px] md:text-xs text-zinc-400 leading-tight" x-text="$store.catalog.selectedItems.length > 0 ? 'Barang Terpilih' : 'Klik barang untuk memilih'"></span>
            </div>
        </div>
        
        <div class="flex items-center gap-2 md:gap-4">
            <button @click="Livewire.dispatch('open-generate-catalog-modal', { data: JSON.parse(JSON.stringify($store.catalog.selectedItems)) })" 
                    :disabled="$store.catalog.selectedItems.length === 0"
                    :class="$store.catalog.selectedItems.length === 0 ? 'opacity-50 cursor-not-allowed bg-zinc-700 hover:bg-zinc-700' : 'bg-indigo-600 hover:bg-indigo-500 active:scale-95 shadow-lg shadow-indigo-600/30'"
                    class="text-white px-3 md:px-4 py-1.5 md:py-2 rounded-xl text-xs md:text-sm font-bold transition flex items-center gap-1.5 md:gap-2">
                <flux:icon.document-duplicate class="w-3.5 h-3.5 md:w-4 md:h-4" />
                <span class="hidden md:inline">Buat Katalog</span>
                <span class="md:hidden">Buat</span>
            </button>
            <button @click="$store.catalog.toggleSelectionMode()" class="text-zinc-400 hover:text-red-400 transition bg-zinc-800 hover:bg-zinc-800/80 p-1.5 md:p-2 rounded-xl" title="Batalkan Mode Katalog">
                <flux:icon.x-mark class="w-4 h-4 md:w-5 md:h-5" />
            </button>
        </div>
    </div>
</div>