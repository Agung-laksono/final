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

    <div x-data x-cloak x-show="$store.catalog.selectedItems.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[99999] flex items-center gap-4 bg-zinc-900 text-white px-6 py-4 rounded-2xl shadow-2xl shadow-zinc-900/40 border border-zinc-700/50">
        <div class="flex items-center gap-3 border-r border-zinc-700 pr-4">
            <div class="bg-indigo-500 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm" x-text="$store.catalog.selectedItems.length"></div>
            <span class="font-semibold text-sm">Barang Terpilih</span>
        </div>
        <button @click="Livewire.dispatch('open-generate-catalog-modal', { data: JSON.parse(JSON.stringify($store.catalog.selectedItems)) })" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-indigo-600/30 transition active:scale-95 flex items-center gap-2">
            <flux:icon.document-duplicate class="w-4 h-4" />
            Buat Katalog
        </button>
        <button @click="$store.catalog.selectedItems = []" class="text-zinc-400 hover:text-zinc-200 transition">
            <flux:icon.x-mark class="w-5 h-5" />
        </button>
    </div>
</div>