<?php

use function Livewire\Volt\{state, layout, title, with, usesPagination, on};
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Warehouse;

usesPagination(theme: 'tailwind');

layout('layouts.app');
title('Riwayat Mutasi');

state([
    'search' => '',
    'type' => '',
    'warehouse_id' => '',
    'date_start' => '',
    'date_end' => '',
    'perPage' => 5,
]);

$loadMore = function () {
    $this->perPage += 5;
};

with(fn () => [
    'movements' => StockMovement::with(['item', 'warehouse', 'user'])
        ->when($this->search, function ($query) {
            $query->whereHas('item', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            })->orWhere('reference_number', 'like', '%' . $this->search . '%');
        })
        ->when($this->type, function ($query) {
            $query->where('type', $this->type);
        })
        ->when($this->warehouse_id, function ($query) {
            $query->where('warehouse_id', $this->warehouse_id);
        })
        ->when($this->date_start, function ($query) {
            $query->whereDate('date', '>=', $this->date_start);
        })
        ->when($this->date_end, function ($query) {
            $query->whereDate('date', '<=', $this->date_end);
        })
        ->latest()
        ->paginate($this->perPage),
    'warehouses' => Warehouse::all(),
]);

$resetFilters = function () {
    $this->reset(['search', 'type', 'warehouse_id', 'date_start', 'date_end']);
    $this->resetPage();
};

on([
    'echo:inventory,InventoryUpdated' => function ($event) {
        $this->resetPage(); // Kembali ke halaman pertama untuk melihat mutasi terbaru
        \Flux\Flux::toast('Terdapat pembaruan data mutasi.', variant: 'info');
    }
]);

?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <flux:heading size="xl">{{ __('Riwayat Mutasi (Kartu Stok)') }}</flux:heading>
            <flux:subheading>{{ __('Pantau semua pergerakan masuk, keluar, dan transfer barang.') }}</flux:subheading>
        </div>
        <flux:button variant="outline" icon="arrow-path" wire:click="resetFilters">
            {{ __('Reset Filter') }}
        </flux:button>
    </div>

    <!-- Filters -->
    <div class="bg-white border dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 p-4 rounded-xl mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="lg:col-span-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari barang atau no referensi..." />
        </div>
        <div>
            <flux:select wire:model.live="warehouse_id" placeholder="Semua Gudang">
                <flux:select.option value="">Semua Gudang</flux:select.option>
                @foreach($warehouses as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div>
            <flux:select wire:model.live="type" placeholder="Semua Tipe">
                <flux:select.option value="">Semua Tipe</flux:select.option>
                <flux:select.option value="in">Masuk (In)</flux:select.option>
                <flux:select.option value="out">Keluar (Out)</flux:select.option>
                <flux:select.option value="transfer_in">Transfer Masuk</flux:select.option>
                <flux:select.option value="transfer_out">Transfer Keluar</flux:select.option>
                <flux:select.option value="adjustment_plus">Penyesuaian (+)</flux:select.option>
                <flux:select.option value="adjustment_minus">Penyesuaian (-)</flux:select.option>
            </flux:select>
        </div>
        <div class="flex items-center gap-2">
            <flux:input type="date" wire:model.live="date_start" class="w-full" aria-label="Mulai Tanggal" />
            <span class="text-zinc-500">-</span>
            <flux:input type="date" wire:model.live="date_end" class="w-full" aria-label="Sampai Tanggal" />
        </div>
    </div>

    <div class="bg-white border dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden pl-3">
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Waktu') }}</flux:table.column>
                    <flux:table.column>{{ __('No. Ref') }}</flux:table.column>
                    <flux:table.column>{{ __('Barang') }}</flux:table.column>
                    <flux:table.column>{{ __('Gudang') }}</flux:table.column>
                    <flux:table.column>{{ __('Tipe') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Qty') }}</flux:table.column>
                    <flux:table.column class="text-center">{{ __('Stok Akhir') }}</flux:table.column>
                    <flux:table.column>{{ __('Petugas') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($movements as $move)
                        <flux:table.row :key="$move->id">
                            <flux:table.cell>
                                <div class="whitespace-nowrap">{{ \Carbon\Carbon::parse($move->created_at)->format('d M Y') }}</div>
                                <div class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($move->created_at)->format('H:i') }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="font-medium whitespace-nowrap">{{ $move->reference_number }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $move->item->name ?? '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $move->item->code ?? '-' }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $move->warehouse->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $typeColors = [
                                        'in' => 'success',
                                        'out' => 'danger',
                                        'transfer_in' => 'info',
                                        'transfer_out' => 'warning',
                                        'adjustment_plus' => 'success',
                                        'adjustment_minus' => 'danger',
                                    ];
                                    $typeLabels = [
                                        'in' => 'Masuk',
                                        'out' => 'Keluar',
                                        'transfer_in' => 'Trf In',
                                        'transfer_out' => 'Trf Out',
                                        'adjustment_plus' => 'Adj (+)',
                                        'adjustment_minus' => 'Adj (-)',
                                    ];
                                    $color = $typeColors[$move->type] ?? 'zinc';
                                    $label = $typeLabels[$move->type] ?? ucfirst($move->type);
                                @endphp
                                <flux:badge :color="$color" size="sm">{{ $label }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-right font-medium">
                                @if($move->quantity > 0)
                                    <span class="text-emerald-600 dark:text-emerald-400">+{{ $move->quantity }}</span>
                                @else
                                    <span class="text-red-600 dark:text-red-400">{{ $move->quantity }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-center font-semibold">{{ $move->stock_after }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="truncate max-w-[120px]" title="{{ $move->user->name ?? '-' }}">
                                    {{ $move->user->name ?? '-' }}
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="text-center text-zinc-500 py-8">
                                {{ __('Belum ada riwayat mutasi stok.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
    
    <x-load-more :paginator="$movements" item-name="mutasi" />
</div>
