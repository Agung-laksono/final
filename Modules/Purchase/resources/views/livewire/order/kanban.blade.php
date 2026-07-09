<?php
use function Livewire\Volt\{state, layout, title, computed, on, usesPagination};
use Modules\Purchase\Models\PurchaseOrder;

usesPagination(theme: 'tailwind');

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
    'viewMode' => session('po_view_mode', 'kanban'),
    'sortBy' => 'created_at',
    'sortDirection' => 'desc',
    'perPage' => 15,
    
    'order_to_archive' => null,
    'show_archive_modal' => false,
]);

$loadMore = function () {
    $this->perPage += 15;
};

$setViewMode = function ($mode) {
    $this->viewMode = $mode;
    session(['po_view_mode' => $mode]);
};

$sort = function ($field) {
    if ($this->sortBy === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $field;
        $this->sortDirection = 'asc';
    }
};

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

$tableOrders = computed(function () {
    $query = PurchaseOrder::with(['vendor', 'creator', 'items'])
        ->select('purchase_orders.*');
        
    if ($this->search) {
        $query->where('po_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('vendor', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    
    $query->orderBy($this->sortBy, $this->sortDirection);
    
    return $query->paginate($this->perPage);
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

<div class="w-full bg-transparent relative">
    <div wire:key="view-kanban-wrapper" class="w-full h-full relative {{ $this->viewMode === 'kanban' ? 'flex flex-col' : 'hidden' }}">
        <x-kanban.board 
            componentId="purchase-order"
            searchModel="search"
            searchPlaceholder="Cari PO / Vendor...">
            
            <x-slot:actions>
                <div class="flex border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden shrink-0">
                    <button type="button" wire:key="kanban-sw-kanban" @click="$wire.setViewMode('kanban')" class="p-1.5 px-2.5 transition-colors {{ $this->viewMode === 'kanban' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Kanban">
                        <flux:icon.view-columns wire:loading.remove wire:target="setViewMode('kanban')" class="w-4 h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('kanban')" class="w-4 h-4 animate-spin" />
                    </button>
                    <button type="button" wire:key="kanban-sw-table" @click="$wire.setViewMode('table')" class="p-1.5 px-2.5 transition-colors {{ $this->viewMode === 'table' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Tabel">
                        <flux:icon.table-cells wire:loading.remove wire:target="setViewMode('table')" class="w-4 h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('table')" class="w-4 h-4 animate-spin" />
                    </button>
                </div>
                <div class="w-px h-6 bg-zinc-200 dark:bg-zinc-700 mx-1 sm:mx-2 hidden sm:block"></div>

                @can('purchase.create')
                    <flux:button variant="primary" icon="plus" href="{{ route('purchase.orders.create') }}" class="px-2.5 sm:px-4 shrink-0" wire:navigate>
                        <span class="hidden sm:inline">Buat PO</span>
                        <span class="sm:hidden">Buat</span>
                    </flux:button>
                @endcan
            </x-slot:actions>
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
                        <flux:button size="sm" variant="subtle" class="!px-1.5 !py-1.5 shrink-0" x-bind:icon="collapsed ? 'arrows-up-down' : 'arrows-right-left'" @click.stop="collapsed = !collapsed" x-bind:title="collapsed ? 'Buka Kolom' : 'Tutup Kolom'" />
                    </div>
                </div>

                {{-- Column Items --}}
                <div x-show="!collapsed" x-transition.opacity.duration.300ms x-init="autoAnimate($el)" class="flex-1 p-3 overflow-y-auto space-y-3" :class="transparent ? 'hide-scroll' : 'custom-scrollbar'">
                    @forelse($this->orders[$statusKey] ?? [] as $po)
                        <div wire:key="po-{{ $po->id }}" 
                             @click="activeId = '{{ $po->id }}'; $dispatch('open-detail-modal', { orderId: {{ $po->id }} })"
                             x-show="processingId !== '{{ $po->id }}'"
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
    </x-kanban.board>
    </div>

    {{-- Table View --}}
    <div wire:key="view-table-wrapper" class="w-full {{ $this->viewMode === 'table' ? 'block' : 'hidden' }}">
        <div x-data="{ lastScroll: 0, show: true }"
             @scroll.window="
                let current = window.pageYOffset;
                if (current > lastScroll && current > 100) { show = false; } 
                else if (current < lastScroll) { show = true; }
                lastScroll = current;
             "
             class="sticky top-0 z-40 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 mb-6 transition-all duration-300 transform shadow-sm"
             :class="show ? 'translate-y-0' : '-translate-y-full'">
            <div class="p-4 sm:px-6">
                <div class="flex flex-col sm:flex-row items-center gap-4">
                    <div class="w-full sm:flex-1 relative">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari PO / Vendor..." class="w-full" />
                    </div>

                    <div class="flex items-center gap-2 sm:gap-4 shrink-0 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
                        <div class="flex border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden shrink-0">
                            <button type="button" wire:key="table-sw-kanban" @click="$wire.setViewMode('kanban')" class="p-1.5 px-3 transition-colors {{ $this->viewMode === 'kanban' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Kanban">
                                <flux:icon.view-columns wire:loading.remove wire:target="setViewMode('kanban')" class="w-4 h-4" />
                                <flux:icon.arrow-path wire:loading wire:target="setViewMode('kanban')" class="w-4 h-4 animate-spin" />
                            </button>
                            <button type="button" wire:key="table-sw-table" @click="$wire.setViewMode('table')" class="p-1.5 px-3 transition-colors {{ $this->viewMode === 'table' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Tabel">
                                <flux:icon.table-cells wire:loading.remove wire:target="setViewMode('table')" class="w-4 h-4" />
                                <flux:icon.arrow-path wire:loading wire:target="setViewMode('table')" class="w-4 h-4 animate-spin" />
                            </button>
                        </div>
                        
                        <div class="w-px h-8 bg-zinc-200 dark:bg-zinc-700 shrink-0"></div>

                        @can('purchase.create')
                            <flux:button variant="primary" icon="plus" href="{{ route('purchase.orders.create') }}" wire:navigate class="shrink-0">
                                <span class="hidden sm:inline">Buat PO</span>
                                <span class="sm:hidden">Buat</span>
                            </flux:button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="px-2 sm:px-6">
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm mb-6">
                <div class="overflow-x-auto min-h-[50vh]">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$sortBy === 'po_number'" :direction="$sortDirection" wire:click="sort('po_number')">No. PO & Tanggal</flux:table.column>
                            <flux:table.column>Vendor</flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'total_amount'" :direction="$sortDirection" wire:click="sort('total_amount')">Total & Pajak</flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
                            <flux:table.column>Aksi</flux:table.column>
                        </flux:table.columns>
        
                        <flux:table.rows>
                            @forelse($this->tableOrders as $order)
                                <flux:table.row :key="$order->id" class="cursor-pointer hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50 transition-colors" x-on:click="$dispatch('open-detail-modal', { orderId: {{ $order->id }} })">
                                    <flux:table.cell>
                                        <div class="pl-2 sm:pl-4">
                                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $order->po_number }}</div>
                                            <div class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($order->order_date)->format('d M Y') }}</div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="flex items-center gap-3">
                                            <flux:avatar src="{{ $order->vendor?->image ? Storage::url($order->vendor->image) : '' }}" fallback="{{ substr($order->vendor?->name ?? '?', 0, 2) }}" size="sm" />
                                            <div>
                                                <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $order->vendor?->name ?? 'Vendor Terhapus' }}</div>
                                                <div class="text-[11px] text-zinc-500 flex items-center gap-1 mt-0.5">
                                                    <flux:icon.user class="w-3 h-3" />
                                                    {{ explode(' ', $order->creator->name ?? '-')[0] }}
                                                </div>
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="font-bold text-zinc-900 dark:text-zinc-100">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <div class="text-[10px] text-zinc-500">{{ $order->items->sum('qty') }} Item</div>
                                            @if($order->pajak)
                                                <flux:badge size="sm" color="amber">Tax</flux:badge>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        @if(array_key_exists($order->status, $columns))
                                            @php $col = $columns[$order->status]; @endphp
                                            <flux:badge size="sm" color="{{ $col['color'] }}">{{ $col['title'] }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc">{{ $order->status }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="flex items-center justify-end gap-2 pr-2 sm:pr-4">
                                            @if(in_array($order->status, ['processing', 'partially_received']))
                                                @can('purchase.order.update')
                                                    <flux:button size="sm" variant="subtle" icon="cube" class="h-8 p-2 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/50" title="Terima Barang" wire:click.stop="$dispatch('open-receipt-modal', { orderId: {{ $order->id }} })">Terima</flux:button>
                                                @endcan
                                            @elseif($order->status === 'completed')
                                                @can('purchase.order.update')
                                                    <flux:button size="sm" variant="subtle" icon="archive-box" class="h-8 p-2 text-zinc-600 hover:text-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800" title="Arsipkan PO" wire:click.stop="confirmArchive({{ $order->id }})">Arsip</flux:button>
                                                @endcan
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5">
                                        <div class="flex flex-col items-center justify-center py-12 text-zinc-500">
                                            <flux:icon.inbox class="w-12 h-12 mb-3 text-zinc-300" />
                                            <p>Tidak ada purchase order.</p>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
            <x-load-more :paginator="$this->tableOrders" item-name="PO" />
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
                <flux:button variant="ghost" wire:click="$set('show_archive_modal', false)"> Batal </flux:button>
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
