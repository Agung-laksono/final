<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Purchase\Models\PurchaseOrder;

layout('layouts.app');
title('Kanban Purchase Order (PO)');

// Definisi Kolom Kanban untuk PO
state([
    'columns' => [
        'processing' => ['title' => 'Diproses Vendor', 'color' => 'blue'],
        'partially_received' => ['title' => 'Diterima Sebagian', 'color' => 'indigo'],
        'completed' => ['title' => 'Selesai', 'color' => 'emerald'],
        'archived' => ['title' => 'Arsip', 'color' => 'slate'],
    ],
    'transparent_columns' => false,
    'search' => '',
    
    'order_to_archive' => null,
    'show_archive_modal' => false,
]);

$orders = computed(function () {
    $query = PurchaseOrder::with('vendor')->latest();
    if ($this->search) {
        $query->where('po_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('vendor', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    return $query->get()->groupBy(function($po) {
        return $po->status ?? 'draft';
    });
});

$updateStatus = function ($orderId, $newStatus) {
    abort_unless(auth()->user()->can('purchase.order.update'), 403, 'Anda tidak memiliki izin untuk mengubah status PO.');
    
    if (!array_key_exists($newStatus, $this->columns)) return;
    
    $po = PurchaseOrder::find($orderId);
    if ($po) {
        $po->status = $newStatus;
        $po->save();
        $this->dispatch('status-updated');
    }
};

$confirmArchive = function ($orderId) {
    $this->order_to_archive = $orderId;
    $this->show_archive_modal = true;
};

$archiveOrder = function () {
    abort_unless(auth()->user()->can('purchase.order.update'), 403, 'Anda tidak memiliki izin.');
    
    if (!$this->order_to_archive) return;
    
    $po = PurchaseOrder::with('items.queueFulfillments.purchaseQueue')->find($this->order_to_archive);
    if ($po) {
        $po->status = 'archived';
        $po->save();
        
        // Sinkronisasi status antrean (Queue) menjadi arsip
        foreach ($po->items as $item) {
            foreach ($item->queueFulfillments as $fulfillment) {
                if ($fulfillment->purchaseQueue && $fulfillment->purchaseQueue->status === 'completed') {
                    $fulfillment->purchaseQueue->status = 'archived';
                    $fulfillment->purchaseQueue->save();
                }
            }
        }
        
        $this->dispatch('status-updated');
        $this->show_archive_modal = false;
        $this->order_to_archive = null;
        \Flux::toast('PO berhasil diarsipkan.', variant: 'success');
    }
};

on(['status-updated' => function () {
    // Kosong saja, tujuannya hanya memancing re-render agar computed $orders dijalankan ulang
}]);

?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="hidden md:block">
            <flux:heading size="xl">Kanban Purchase Order (PO)</flux:heading>
            <flux:subheading>Atur dan pantau progres dokumen pemesanan ke Supplier/Vendor secara visual.</flux:subheading>
        </div>
        
        <div class="flex items-center gap-3 w-full md:w-auto">
            <div class="flex-1 min-w-0 md:w-64">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari PO / Vendor..." />
            </div>

            <div class="flex items-center gap-3 shrink-0">
                {{-- Toggle Transparan --}}
                <div class="hidden sm:flex" title="Mode Transparan">
                    <flux:switch wire:model.live="transparent_columns" label="Transparan" />
                </div>
                <div class="flex sm:hidden" title="Mode Transparan">
                    <flux:switch wire:model.live="transparent_columns" />
                </div>

                @can('purchase.create')
                    {{-- Tombol Add --}}
                    <flux:button variant="primary" icon="plus" href="{{ route('purchase.orders.create') }}" class="px-3 sm:px-4 shrink-0">
                        <span class="hidden sm:inline">Buat PO Baru</span>
                    </flux:button>
                @endcan
            </div>
        </div>
    </div>

    {{-- Kanban Board Area --}}
    <div class="flex justify-start gap-6 overflow-x-auto pb-4 h-[calc(100vh-12rem)] -mx-4 px-4 md:mx-0 md:px-0 snap-x snap-mandatory scroll-smooth custom-scrollbar items-stretch">
        @foreach($columns as $statusKey => $column)
            <div x-data="{ collapsed: {{ $statusKey === 'archived' ? 'true' : 'false' }} }"
                 class="flex-shrink-0 h-full max-h-full rounded-xl flex flex-col transition-all duration-300 snap-center {{ $transparent_columns ? '' : 'bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-800' }}"
                 :class="collapsed ? 'w-16' : 'w-80'"
                 wire:key="column-{{ $statusKey }}">
                
                {{-- Column Header --}}
                <div class="p-4 flex justify-between items-center rounded-t-xl transition-all duration-300 {{ $transparent_columns ? '' : 'bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800' }}"
                     :class="collapsed ? 'flex-col gap-4' : ''">
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500 shadow-[0_0_8px_rgba(0,0,0,0.5)] shadow-{{ $column['color'] }}-500/50 shrink-0"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 transition-all duration-300 whitespace-nowrap"
                            :class="collapsed ? 'vertical-text tracking-widest mt-2' : ''">{{ $column['title'] }}</h3>
                    </div>
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800 shrink-0">{{ count($this->orders[$statusKey] ?? []) }}</flux:badge>
                        @if($statusKey === 'archived')
                            <button @click="collapsed = !collapsed" class="text-zinc-400 hover:text-zinc-600 transition-colors shrink-0" title="Toggle Kolom Arsip">
                                <flux:icon.arrows-right-left class="w-4 h-4" />
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Column Items --}}
                <div x-show="!collapsed" x-transition.opacity.duration.300ms class="flex-1 p-3 overflow-y-auto space-y-3 custom-scrollbar">
                    @forelse($this->orders[$statusKey] ?? [] as $po)
                        <div wire:click="$dispatch('open-detail-modal', { orderId: {{ $po->id }} })"
                             class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 hover:-translate-y-1 hover:shadow-md hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-all duration-300 group relative cursor-pointer">
                            
                            {{-- Header Card --}}
                            <div class="flex justify-between items-start mb-3">
                                <span class="font-mono text-xs font-bold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 px-2 py-1 rounded-md">
                                    {{ $po->po_number }}
                                </span>
                                <div class="flex items-center text-[10px] text-zinc-400 font-medium">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    {{ \Carbon\Carbon::parse($po->order_date)->format('d M Y') }}
                                </div>
                            </div>

                            {{-- Vendor Info --}}
                            <div class="flex items-center gap-3 mb-4">
                                <div class="relative">
                                    <flux:avatar src="{{ $po->vendor?->image ? Storage::url($po->vendor->image) : '' }}" fallback="{{ substr($po->vendor?->name ?? '?', 0, 2) }}" size="sm" class="ring-2 ring-white dark:ring-zinc-900 shadow-sm" />
                                </div>
                                <div class="flex flex-col overflow-hidden">
                                    <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200 truncate">
                                        {{ $po->vendor?->name ?? 'Vendor Terhapus' }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400 truncate">Supplier</span>
                                </div>
                            </div>
                            
                            {{-- Total & Tax --}}
                            <div class="flex items-end justify-between pt-3 border-t border-dashed border-zinc-200 dark:border-zinc-700">
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-0.5">Total Nilai</span>
                                    <span class="text-lg font-black text-zinc-900 dark:text-zinc-100 tracking-tight leading-none">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center">
                                    @if($po->pajak)
                                        <flux:badge size="sm" color="amber" class="font-bold">Tax</flux:badge>
                                    @endif
                                </div>
                            </div>

                            {{-- Action Buttons --}}
                                @if(in_array($statusKey, ['processing', 'partially_received']))
                                    <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                        @can('purchase.order.update')
                                            <flux:button variant="primary" class="w-full" icon="cube" wire:click.stop="$dispatch('open-receipt-modal', { orderId: {{ $po->id }} })">
                                                Terima Barang
                                            </flux:button>
                                        @else
                                            <flux:button disabled class="w-full" icon="cube">
                                                Terima Barang
                                            </flux:button>
                                        @endcan
                                    </div>
                                @endif
                                
                                @if($statusKey === 'completed')
                                    <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                        @can('purchase.order.update')
                                            <flux:button variant="ghost" class="w-full text-zinc-500 hover:text-zinc-700" icon="archive-box" wire:click.stop="confirmArchive({{ $po->id }})">
                                                Arsipkan PO
                                            </flux:button>
                                        @endcan
                                    </div>
                                @endif
                        </div>
                    @empty
                        <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400 dark:text-zinc-500">
                            Kosong
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    {{-- Confirm Archive Modal --}}
    <flux:modal wire:model="show_archive_modal" class="min-w-[22rem]">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-10 h-10 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-full flex items-center justify-center">
                    <flux:icon.archive-box class="w-5 h-5" />
                </div>
                <div>
                    <flux:heading size="lg">Arsipkan PO?</flux:heading>
                    <flux:subheading class="mt-2 text-sm">
                        Purchase Order ini akan dipindahkan ke kolom Arsip agar Kanban tetap rapi. Seluruh antrean yang tergabung di dalamnya juga akan ikut diarsipkan jika sudah selesai. Lanjutkan?
                    </flux:subheading>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('show_archive_modal', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="archiveOrder">Ya, Arsipkan</flux:button>
            </div>
        </div>
    </flux:modal>

    <livewire:order.receipt-modal />
    <livewire:order.detail-modal />

    <livewire:print-label-modal />
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 10px;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #334155;
    }
    .vertical-text {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
    }
</style>
