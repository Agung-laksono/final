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
    </div>


    <!-- Print Label Modal -->
    <livewire:print-label-modal />

    {{-- FAB: Tombol Tambah Barang (di luar item-list, tidak terdampak re-render) --}}
    @can('inventory.item.create')
    <div
        x-data="{ show: false }"
        x-init="setTimeout(() => show = true, 500)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 scale-90"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3"
        style="display: none;"
    >
        {{-- Label tooltip --}}
        <div
            x-data="{ hover: false }"
            @mouseenter="hover = true"
            @mouseleave="hover = false"
            class="relative"
        >
            <div
                x-show="hover"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-x-2"
                x-transition:enter-end="opacity-100 translate-x-0"
                class="absolute right-full mr-3 top-1/2 -translate-y-1/2 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 text-sm font-semibold px-3 py-1.5 rounded-lg whitespace-nowrap shadow-lg pointer-events-none"
            >
                Tambah Barang
                <div class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 w-2 h-2 bg-zinc-900 dark:bg-zinc-100 rotate-45"></div>
            </div>

            <button
                @click="
                    console.log('FAB Clicked!');
                    try {
                        Livewire.dispatch('open-item-modal');
                        console.log('Livewire.dispatch executed.');
                    } catch (e) {
                        console.error('Dispatch failed', e);
                    }
                "
                class="w-14 h-14 rounded-full bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white shadow-2xl shadow-indigo-500/40 flex items-center justify-center transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-4 focus:ring-indigo-500/30"
                title="Tambah Barang Baru"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </button>
        </div>
    </div>
    @endcan

</div>