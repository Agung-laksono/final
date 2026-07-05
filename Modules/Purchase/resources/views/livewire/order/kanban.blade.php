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
        \App\Events\KanbanUpdated::safeDispatch('purchase_order');
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
        \App\Events\KanbanUpdated::safeDispatch('purchase_order');
        $this->show_archive_modal = false;
        $this->order_to_archive = null;
        \Flux::toast('PO berhasil diarsipkan.', variant: 'success');
    }
};

on([
    'status-updated' => function () {
        // Kosong saja, tujuannya hanya memancing re-render agar computed $orders dijalankan ulang
    },
    'echo:kanban.purchase_order,KanbanUpdated' => function () {}
]);

?>

<div class="kanban-root relative flex flex-col w-full" 
     x-data="{ 
        showHeader: $persist(true).as('kanban-order-header-user-{{ auth()->id() }}'),
        transparent: $persist(false).as('kanban-order-transparent-user-{{ auth()->id() }}')
     }" 
     style="height: 100vh; overflow: hidden;">
    
    <style>
        /* Paksa hilangkan padding bawaan layout KHUSUS untuk halaman Kanban ini */
        *:has(> .kanban-root), *:has(> div > .kanban-root) {
            padding: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 0 !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            overflow: hidden !important;
        }
        main[data-flux-main] {
            padding: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 0 !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            overflow: hidden !important;
        }
        body {
            overflow: hidden !important;
        }
        
        /* Menyembunyikan scrollbar tapi tetap bisa digulir */
        .hide-scroll::-webkit-scrollbar {
            display: none;
        }
        .hide-scroll {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
     
    {{-- Floating Show Header Button --}}
    <div class="absolute top-2 right-2 sm:top-4 sm:right-6 z-[110]" x-show="!showHeader" x-transition x-cloak>
        <flux:button variant="subtle" class="rounded-full shadow-lg bg-white/90 dark:bg-zinc-800/90 backdrop-blur border border-zinc-200 dark:border-zinc-700 w-10 h-10 p-0 flex items-center justify-center" @click="showHeader = true" title="Tampilkan Alat">
            <flux:icon.chevron-down class="w-5 h-5 text-zinc-500" />
        </flux:button>
    </div>

    {{-- Floating Controls (Full Width) --}}
    <div class="absolute top-2 left-2 right-2 sm:top-4 sm:left-4 sm:right-4 z-[60] flex items-center justify-between gap-2 sm:gap-4 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md px-2 py-2 sm:px-4 sm:py-3 rounded-2xl shadow-sm border border-zinc-200/50 dark:border-zinc-800/50" x-show="showHeader" x-transition>
        
        <div class="flex-1 min-w-0 max-w-md">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari PO / Vendor..." />
        </div>

        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
            <div class="hidden sm:flex items-center mr-2" title="Mode Transparan">
                <flux:switch x-model="transparent" label="Transparan" />
            </div>

            @can('purchase.create')
                <div class="w-px h-6 bg-zinc-200 dark:bg-zinc-700 mx-1 sm:mx-2 hidden sm:block"></div>
                <flux:button variant="primary" icon="plus" href="{{ route('purchase.orders.create') }}" class="px-2.5 sm:px-4 shrink-0" wire:navigate>
                    <span class="hidden sm:inline">Buat PO</span>
                    <span class="sm:hidden">Buat</span>
                </flux:button>
            @endcan

            <flux:button variant="subtle" class="px-2.5 sm:px-3 text-zinc-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 ml-1 sm:ml-2" title="Sembunyikan Alat" @click="showHeader = false">
                <flux:icon.eye-slash class="w-5 h-5" />
            </flux:button>
        </div>
    </div>

    {{-- Kanban Board Area --}}
    <div class="flex-1 min-h-0 flex flex-col px-0 lg:px-6 transition-all duration-300"
         :class="showHeader ? 'pt-16 sm:pt-20 lg:pt-24' : 'pt-2 lg:pt-6'">
        <div class="flex-1 min-h-0 overflow-x-auto pb-2 lg:pb-4 snap-x snap-mandatory scroll-smooth custom-scrollbar">
            <div class="flex justify-start gap-3 sm:gap-4 lg:gap-6 items-stretch min-w-max h-full px-2 lg:px-0">
        @foreach($columns as $statusKey => $column)
            <div x-data="{ collapsed: $persist({{ $statusKey === 'archived' ? 'true' : 'false' }}).as('kanban-col-order-{{ $statusKey }}-user-{{ auth()->id() }}') }"
                 style="height: 100%; display: flex; flex-direction: column;"
                 class="flex-shrink-0 rounded-xl transition-all duration-300 snap-center"
                 :class="(transparent ? '' : 'bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-800 ') + (collapsed ? 'w-16 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800/80' : 'w-80')"
                 @click="if(collapsed) collapsed = false"
                 wire:key="column-{{ $statusKey }}">
                
                {{-- Column Header --}}
                <div class="p-4 flex justify-between items-center rounded-t-xl transition-all duration-300"
                     :class="(transparent ? '' : 'bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 ') + (collapsed ? 'flex-col gap-4 h-full pb-8' : '')">
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500 shadow-[0_0_8px_rgba(0,0,0,0.5)] shadow-{{ $column['color'] }}-500/50 shrink-0"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 transition-all duration-300 whitespace-nowrap"
                            :class="collapsed ? 'vertical-text tracking-widest mt-2' : ''">{{ $column['title'] }}</h3>
                    </div>
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800 shrink-0">{{ count($this->orders[$statusKey] ?? []) }}</flux:badge>
                        <button @click.stop="collapsed = !collapsed" class="text-zinc-400 hover:text-zinc-600 transition-colors shrink-0" x-bind:title="collapsed ? 'Buka Kolom' : 'Tutup Kolom'">
                            <flux:icon.arrows-right-left class="w-4 h-4" x-bind:class="collapsed ? 'rotate-90' : ''" />
                        </button>
                    </div>
                </div>

                {{-- Column Items --}}
                <div x-show="!collapsed" x-transition.opacity.duration.300ms class="flex-1 p-3 overflow-y-auto space-y-3" :class="transparent ? 'hide-scroll' : 'custom-scrollbar'">
                    @forelse($this->orders[$statusKey] ?? [] as $po)
                        <div wire:click="$dispatch('open-detail-modal', { orderId: {{ $po->id }} })"
                             class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 hover:-translate-y-1 hover:shadow-md hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-all duration-300 group relative cursor-pointer">
                            
                            {{-- Header Card --}}
                            <div class="flex justify-between items-start mb-3">
                                <span class="font-mono text-xs font-bold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 px-2 py-1 rounded-md">
                                    {{ $po->po_number }}
                                </span>
                                <div class="flex items-center text-[10px] text-zinc-400 font-medium">
                                    @if($po->creator)
                                        <flux:avatar src="" fallback="{{ substr($po->creator->name, 0, 2) }}" size="xs" class="w-4 h-4 mr-1 text-[8px]" />
                                        <span class="mr-2" title="Dibuat oleh {{ $po->creator->name }}">{{ strtok($po->creator->name, ' ') }}</span>
                                    @endif
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
        </div>
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
</div>
