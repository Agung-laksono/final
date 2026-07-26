<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Production\Models\ProductionOrder;

layout('layouts.app');
title('Alokasi Kedatangan (Dispatcher)');

state([
    'search' => '',
]);

$receipts = computed(function () {
    $query = ProductionOrder::with(['item', 'item.type', 'purchaseOrder', 'purchaseOrder.vendor'])
        ->where('status', 'receiving')
        ->whereNull('target_warehouse_id')
        ->latest();
        
    if ($this->search) {
        $query->where('order_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('item', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    
    return $query->get();
});

on(['status-updated' => function () {}]);

?>

<div class="space-y-6">
    <x-table.header searchModel="search" searchPlaceholder="Cari PO, Ref, atau Barang..."></x-table.header>

    <x-table.wrapper>
        <flux:table class="table-mobile-cards">
            <flux:table.columns>
                <flux:table.column>Tanggal & SPK</flux:table.column>
                <flux:table.column>Barang & Kuantitas</flux:table.column>
                <flux:table.column>Vendor/Asal</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->receipts as $req)
                    <flux:table.row class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <flux:table.cell>
                            <div class="font-mono text-sm text-zinc-900 dark:text-zinc-100">{{ $req->order_number }}</div>
                            <div class="text-[11px] text-zinc-500">{{ $req->updated_at->format('d M Y, H:i') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-start gap-3 mt-1 sm:mt-0">
                                <div class="w-10 h-10 rounded-md bg-zinc-100 overflow-hidden border border-zinc-200 shrink-0">
                                    @if ($req->item?->image)
                                        <img src="{{ asset('storage/' . $req->item->image) }}" loading="lazy" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                            <flux:icon.photo class="w-5 h-5" />
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-bold text-sm text-zinc-900 dark:text-white line-clamp-1">{{ $req->item->name }}</div>
                                    <div class="text-[11px] text-zinc-500 flex items-center gap-1.5 mt-0.5">
                                        <span class="font-bold text-blue-600 dark:text-blue-400">{{ $req->requested_qty }}</span>
                                        <span>{{ $req->item->unit->name ?? 'pcs' }}</span>
                                        <span class="mx-1">•</span>
                                        <span>{{ $req->item->type->name ?? '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-[11px] text-zinc-600 dark:text-zinc-400 mt-1 sm:mt-0">
                                <span class="text-zinc-500">Dari:</span>
                                @if($req->purchaseOrder)
                                    {{ $req->purchaseOrder->vendor->name ?? 'Vendor Maklon' }} (PO: {{ $req->purchaseOrder->po_number }})
                                @else
                                    <span class="text-indigo-600 dark:text-indigo-400 font-medium">Internal Produksi</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-2 pr-2 sm:pr-4 mt-1 sm:mt-0">
                                <flux:button size="sm" variant="primary" icon="map-pin" wire:click="$dispatch('open-allocate-modal', { orderId: {{ $req->id }} })">Alokasikan Rute</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
                            <flux:icon.inbox class="w-8 h-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                            Tidak ada barang yang perlu dialokasikan.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-table.wrapper>
    
    <livewire:dispatch.allocate-modal />
</div>
