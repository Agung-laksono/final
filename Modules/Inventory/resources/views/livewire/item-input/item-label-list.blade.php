<?php

use function Livewire\Volt\{state, with, usesPagination, on};
use Modules\Inventory\Models\ItemLabel;

usesPagination(theme: 'tailwind');

on(['echo:inventory,InventoryUpdated' => function () {}]);

state([
    'itemId' => null, 
    'search' => '', 
    'status' => '', 
    'warehouse_id' => '',
    'perPage' => 10
]);

$loadMore = function () {
    $this->perPage += 10;
};

with(fn () => [
    'labels' => ItemLabel::with('warehouse')
        ->where('item_id', $this->itemId)
        ->when($this->search, function ($query) {
            $query->where('label_code', 'like', '%' . $this->search . '%')
                  ->orWhere('notes', 'like', '%' . $this->search . '%');
        })
        ->when($this->status, function ($query) {
            $query->where('status', $this->status);
        })
        ->when($this->warehouse_id, function ($query) {
            $query->where('warehouse_id', $this->warehouse_id);
        })
        ->latest()
        ->paginate($this->perPage),
    'warehouses' => \Modules\Inventory\Models\Warehouse::all(),
]);

?>

<div>
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari barcode / catatan..." size="sm" />
        </div>
        <div class="w-full sm:w-1/4">
            <flux:select wire:model.live="status" placeholder="Semua Status" size="sm">
                <flux:select.option value="">Semua Status</flux:select.option>
                <flux:select.option value="in_stock">Di Gudang (In Stock)</flux:select.option>
                <flux:select.option value="sold">Terjual (Sold)</flux:select.option>
                <flux:select.option value="broken">Rusak (Broken)</flux:select.option>
                <flux:select.option value="in_transit">Dalam Pengiriman (Transit)</flux:select.option>
            </flux:select>
        </div>
        <div class="w-full sm:w-1/4">
            <flux:select wire:model.live="warehouse_id" placeholder="Semua Lokasi" size="sm">
                <flux:select.option value="">Semua Lokasi</flux:select.option>
                @foreach($warehouses as $wh)
                    <flux:select.option value="{{ $wh->id }}">{{ $wh->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
        <flux:table class=" pl-3">
            <flux:table.columns>
                <flux:table.column>{{ __('QR Code') }}</flux:table.column>
                <flux:table.column>{{ __('Barcode/Label') }}</flux:table.column>
                <flux:table.column>{{ __('Lokasi Gudang') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Catatan') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($labels as $label)
                    <flux:table.row :key="$label->id">
                        <flux:table.cell>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={{ urlencode($label->label_code) }}" 
                                 class="w-10 h-10 rounded bg-white p-0.5 border border-zinc-200 dark:border-zinc-700 shadow-sm" 
                                 alt="QR Code" loading="lazy">
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm font-semibold">{{ $label->label_code }}</flux:table.cell>
                        <flux:table.cell>{{ $label->warehouse->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $statusColors = [
                                    'in_stock' => 'success',
                                    'sold' => 'zinc',
                                    'broken' => 'danger',
                                    'in_transit' => 'warning',
                                ];
                                $statusLabels = [
                                    'in_stock' => 'Tersedia',
                                    'sold' => 'Terjual',
                                    'broken' => 'Rusak',
                                    'in_transit' => 'Transit',
                                ];
                                $color = $statusColors[$label->status] ?? 'zinc';
                                $text = $statusLabels[$label->status] ?? $label->status;
                            @endphp
                            <flux:badge :color="$color" size="sm">{{ $text }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500 max-w-[150px] truncate" title="{{ $label->notes }}">
                            {{ $label->notes ?? '-' }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500 py-6">
                            {{ __('Tidak ada data label/barcode.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    
    <x-load-more :paginator="$labels" item-name="label" />
</div>
