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
    'perPage' => 10,
    'selectedLabels' => []
]);

$loadMore = function () {
    $this->perPage += 10;
};

$toggleSelectAll = function ($isChecked) {
    if ($isChecked) {
        $this->selectedLabels = ItemLabel::where('item_id', $this->itemId)
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
            ->paginate($this->perPage)
            ->pluck('id')->map(fn($id) => (string)$id)->toArray();
    } else {
        $this->selectedLabels = [];
    }
};

$printSelected = function () {
    if (empty($this->selectedLabels)) return;
    $this->dispatch('open-print-labels', labelIds: $this->selectedLabels);
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
                <flux:table.column class="w-10">
                    <flux:checkbox wire:change="toggleSelectAll($event.target.checked)" :checked="count($labels) > 0 && count($selectedLabels) === count($labels)" />
                </flux:table.column>
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
                            <flux:checkbox wire:model.live="selectedLabels" value="{{ $label->id }}" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="w-10 h-10 rounded bg-white p-0.5 border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden flex items-center justify-center"
                                 wire:ignore
                                 x-data="{ code: '{{ $label->label_code }}' }" 
                                 x-init="
                                     let attempt = 0;
                                     let renderQR = () => {
                                         if(typeof QRCode !== 'undefined') {
                                             new QRCode($el, { text: code, width: 36, height: 36, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
                                         } else if (attempt < 20) {
                                             attempt++;
                                             setTimeout(renderQR, 150);
                                         }
                                     };
                                     $nextTick(renderQR);
                                 "
                                 title="{{ $label->label_code }}">
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm font-semibold">{{ $label->label_code }}</flux:table.cell>
                        <flux:table.cell>{{ $label->warehouse->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $statusColors = [
                                    'in_stock' => 'emerald',
                                    'sold' => 'zinc',
                                    'broken' => 'red',
                                    'in_transit' => 'amber',
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
                        <flux:table.cell colspan="6" class="text-center text-zinc-500 py-6">
                            {{ __('Tidak ada data label/barcode.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    
    <x-load-more :paginator="$labels" item-name="label" />

    @if(count($selectedLabels) > 0)
        <div class="sticky bottom-0 z-10 mt-4 bg-emerald-50 dark:bg-emerald-900/40 border border-emerald-200 dark:border-emerald-800 rounded-lg p-3 flex items-center justify-between shadow-lg backdrop-blur-md">
            <span class="text-sm font-medium text-emerald-800 dark:text-emerald-200">{{ count($selectedLabels) }} label terpilih</span>
            <flux:button wire:click="printSelected" icon="printer" variant="primary" size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600">
                Cetak Label
            </flux:button>
        </div>
    @endif
</div>
