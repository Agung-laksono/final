<?php

use function Livewire\Volt\{state, layout, title, with, usesPagination, on};
use Modules\Inventory\Models\StockTransfer;
use Flux\Flux;

usesPagination(theme: 'tailwind');

layout('layouts.app');
title('Transfer Barang');

state([
    'search' => '',
    'perPage' => 3,
]);

$loadMore = function () {
    $this->perPage += 15;
};

on([
    'transfer-saved' => function () {},
    'transfer-deleted' => function () {},
    'echo:inventory,InventoryUpdated' => function ($event) {
        // Triggered via WebSockets when another user (or tab) saves a transfer
        \Flux\Flux::toast('Riwayat transfer diperbarui dari server.', variant: 'success');
    }
]);

with(fn () => [
    'transfers' => StockTransfer::with(['fromWarehouse', 'toWarehouse', 'items.item'])
        ->when($this->search, function ($query) {
            $query->where('reference_number', 'like', '%' . $this->search . '%');
        })
        ->latest()
        ->paginate($this->perPage)
]);

?>

<div>
    <x-sticky-header class="flex flex-col sm:flex-row justify-end md:justify-between items-start sm:items-center mb-6 gap-4">
        <div class="hidden md:block w-max">
            <flux:heading size="lg">{{ __('Transfer Antar Gudang') }}</flux:heading>
            <flux:subheading>{{ __('Kelola perpindahan stok antar gudang.') }}</flux:subheading>
        </div>
        
        <div class="flex items-center gap-3 w-full sm:w-auto">
            {{-- Search Bar --}}
            <div class="flex-1 sm:flex-none sm:w-72 relative">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari No. Referensi..." />
            </div>

            @can('inventory.transfer.create')
            <flux:modal.trigger name="create-transfer-modal">
                <flux:button variant="primary" icon="plus" class="px-3 sm:px-4">
                    <span class="hidden sm:inline">{{ __('Transfer Baru') }}</span>
                </flux:button>
            </flux:modal.trigger>
            @endcan
        </div>
    </x-sticky-header>

    <x-table.wrapper class="mb-6">
        <flux:table class="table-mobile-cards">
            <flux:table.columns>
                <flux:table.column>{{ __('Dokumen') }}</flux:table.column>
                <flux:table.column>{{ __('Rute Transfer') }}</flux:table.column>
                <flux:table.column>{{ __('Total Barang') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($transfers as $transfer)
                    <flux:table.row :key="$transfer->id" class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors" wire:click="$dispatch('open-transfer-detail', { id: {{ $transfer->id }} })">
                        <flux:table.cell>
                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100 whitespace-nowrap">{{ $transfer->reference_number }}</div>
                            <div class="text-[11px] text-zinc-500">{{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2 text-sm mt-1 sm:mt-0">
                                <span class="text-zinc-600 dark:text-zinc-400">{{ $transfer->fromWarehouse->name ?? '-' }}</span>
                                <flux:icon.arrow-right class="w-3 h-3 text-zinc-400 shrink-0" />
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $transfer->toWarehouse->name ?? '-' }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-sm text-zinc-600 dark:text-zinc-400 mt-1 sm:mt-0">{{ $transfer->items->count() }} Jenis</span>
                        </flux:table.cell>
                        <flux:table.cell class="card-status-overlay">
                            @if($transfer->status === 'completed')
                                <flux:badge color="success" size="sm">{{ __('Selesai') }}</flux:badge>
                            @elseif($transfer->status === 'pending')
                                <flux:badge color="warning" size="sm">{{ __('Pending') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ ucfirst($transfer->status) }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500 py-8">
                            <flux:icon.inbox class="w-8 h-8 mx-auto mb-2 text-zinc-300" />
                            {{ __('Belum ada data transfer.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-table.wrapper>
    
    {{-- Progress Bar & Load More --}}
    <div class="mt-4">
        <x-load-more :paginator="$transfers" item-name="transfer" />
    </div>

    <!-- Modal Form Transfer -->
    <flux:modal name="create-transfer-modal" class="md:w-[90vw] lg:w-[70vw] max-w-7xl" style="max-width: 95vw;" scroll="body">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Buat Transfer Baru') }}</flux:heading>
                <flux:subheading>{{ __('Pindahkan stok dari satu gudang ke gudang lain.') }}</flux:subheading>
            </div>
            
            <livewire:item-transfer.transfer-form />
            
        </div>
    </flux:modal>

    <!-- Modal Detail Transfer -->
    <livewire:item-transfer.transfer-detail />
</div>
