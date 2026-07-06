<?php
use function Livewire\Volt\{state, layout, title, computed, on, usesPagination};
use Modules\Purchase\Models\PurchaseQueue;
use Modules\Inventory\Models\Item;

usesPagination(theme: 'tailwind');

layout('layouts.app');
title('Kanban Permintaan Pembelian');

// Definisi Kolom Kanban
state([
    'columns' => [
        'approved' => ['title' => 'Disetujui (Antre)', 'color' => 'blue'],
        'ordered' => ['title' => 'Sudah Dipesan (PO)', 'color' => 'amber'],
        'completed' => ['title' => 'Selesai / Ready', 'color' => 'emerald'],
        'rejected' => ['title' => 'Ditolak', 'color' => 'red'],
        'archived' => ['title' => 'Arsip', 'color' => 'slate'],
    ],
    'transparent_columns' => false,
    'search' => '',
    'viewMode' => session('pq_view_mode', 'kanban'),
    'sortBy' => 'created_at',
    'sortDirection' => 'desc',
    'perPage' => 15,
]);

$loadMore = function () {
    $this->perPage += 15;
};

$setViewMode = function ($mode) {
    $this->viewMode = $mode;
    session(['pq_view_mode' => $mode]);
};

$sort = function ($field) {
    if ($this->sortBy === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $field;
        $this->sortDirection = 'asc';
    }
};

$queues = computed(function () {
    $query = PurchaseQueue::with(['item', 'fulfillments.purchaseOrderItem.purchaseOrder.vendor'])->latest();
    if ($this->search) {
        $query->whereHas('item', function($q) {
            $q->where('name', 'like', '%' . $this->search . '%')
              ->orWhere('code', 'like', '%' . $this->search . '%');
        });
    }
    return $query->get()->groupBy(function($q) {
        return $q->status ?? 'approved';
    });
});

$tableQueues = computed(function () {
    $query = PurchaseQueue::with(['item', 'creator', 'fulfillments.purchaseOrderItem.purchaseOrder.vendor'])
        ->select('purchase_queues.*');
        
    if ($this->search) {
        $query->whereHas('item', function($q) {
            $q->where('name', 'like', '%' . $this->search . '%')
              ->orWhere('code', 'like', '%' . $this->search . '%');
        });
    }
    
    $query->orderBy($this->sortBy, $this->sortDirection);
    
    return $query->paginate($this->perPage);
});

$updateStatus = function ($queueId, $newStatus) {
    if (!array_key_exists($newStatus, $this->columns)) return;
    
    $queue = PurchaseQueue::find($queueId);
    if ($queue) {
        abort_unless(auth()->user()->can('purchase.queue.update'), 403, 'Anda tidak memiliki izin untuk menggeser antrean.');

        $queue->status = $newStatus;
        $queue->save();
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('purchase_queue');
        \Flux::toast('Status berhasil diperbarui.', variant: 'success');
    }
};

$rejectQueue = function ($queueId) {
    abort_unless(auth()->user()->can('purchase.queue.update'), 403, 'Anda tidak memiliki izin untuk menolak antrean.');
    $queue = PurchaseQueue::find($queueId);
    if ($queue) {
        $queue->status = 'rejected';
        $queue->approved_qty = 0;
        $queue->save();
        $this->dispatch('status-updated');
    }
};

on(['status-updated' => function () {
    // Kosong saja, tujuannya hanya memancing re-render agar computed $queues dijalankan ulang
}]);

?>

<div class="w-full bg-transparent relative">
    <div wire:key="view-kanban-wrapper" class="w-full h-full relative {{ $this->viewMode === 'kanban' ? 'flex flex-col' : 'hidden' }}">
        <x-kanban.board 
            componentId="purchase-queue"
            searchModel="search"
            searchPlaceholder="Cari nama atau kode barang...">
            
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
            </x-slot:actions>
        @foreach($columns as $statusKey => $column)
            <div x-data="{ collapsed: $persist({{ in_array($statusKey, ['archived', 'rejected']) ? 'true' : 'false' }}).as('kanban-col-queue-{{ $statusKey }}-user-{{ auth()->id() }}') }"
                 style="height: 100%; display: flex; flex-direction: column;"
                 class="h-full max-h-full rounded-2xl overflow-hidden snap-center transition-all duration-300 flex-col shrink-0" 
                 :class="(transparent ? '' : 'bg-zinc-50 dark:bg-zinc-800/30 border border-zinc-200 dark:border-zinc-800/50 shadow-sm ') + (collapsed ? 'w-16 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800/80' : 'w-80')"
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
                        @if($statusKey === 'approved' && count($this->queues['approved'] ?? []) > 0)
                            <div x-show="!collapsed">
                                <flux:button size="xs" variant="primary" icon="document-plus" wire:click="$dispatch('open-consolidation-modal')" class="!h-6 !px-2 text-[10px]">Buat PO</flux:button>
                            </div>
                        @endif
                        <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800 shrink-0">{{ count($this->queues[$statusKey] ?? []) }}</flux:badge>
                        <button @click.stop="collapsed = !collapsed" class="text-zinc-400 hover:text-zinc-600 transition-colors shrink-0" x-bind:title="collapsed ? 'Buka Kolom' : 'Tutup Kolom'">
                            <flux:icon.arrows-right-left class="w-4 h-4" x-bind:class="collapsed ? 'rotate-90' : ''" />
                        </button>
                    </div>
                </div>

                {{-- Column Items --}}
                <div x-show="!collapsed" x-transition.opacity.duration.300ms x-init="autoAnimate($el)" class="flex-1 p-3 overflow-y-auto space-y-3" :class="transparent ? 'hide-scroll' : 'custom-scrollbar'">
                    @forelse($this->queues[$statusKey] ?? [] as $queue)
                            @php
                                $sourceBadge = match($queue->source_type) {
                                    'low_stock' => [
                                        'color' => 'red',
                                        'icon' => '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
                                        'label' => 'Stok Menipis'
                                    ],
                                    'sales' => [
                                        'color' => 'emerald',
                                        'icon' => '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>',
                                        'label' => 'Pesanan Jual'
                                    ],
                                    default => [
                                        'color' => 'zinc',
                                        'icon' => '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>',
                                        'label' => 'Manual'
                                    ]
                                };
                            @endphp
                        <div wire:key="queue-{{ $queue->id }}" 
                             @click="activeId = '{{ $queue->id }}'"
                             x-show="processingId !== '{{ $queue->id }}'"
                             class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 hover:-translate-y-1 hover:shadow-md hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-all duration-300 group relative flex flex-col">
                            
                            {{-- Header Card --}}
                            <div class="flex justify-between items-start mb-3">
                                <span class="font-mono text-[11px] font-bold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 px-2 py-0.5 rounded-md">
                                    #ANT-{{ str_pad($queue->id, 4, '0', STR_PAD_LEFT) }}
                                </span>
                                <div class="flex items-center text-[10px] text-zinc-400 font-medium">
                                    @if($queue->creator)
                                        <flux:avatar src="" fallback="{{ substr($queue->creator->name, 0, 2) }}" size="xs" class="w-4 h-4 mr-1 text-[8px]" />
                                        <span class="mr-2" title="Dibuat oleh {{ $queue->creator->name }}">{{ strtok($queue->creator->name, ' ') }}</span>
                                    @else
                                        <flux:icon.cpu-chip class="w-3 h-3 mr-1" />
                                        <span class="mr-2" title="Dibuat otomatis oleh Sistem">Sistem</span>
                                    @endif
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    {{ $queue->created_at->diffForHumans() }}
                                </div>
                            </div>

                            {{-- Source Badge --}}
                            <div class="mb-2">
                                <span class="inline-flex items-center rounded-md bg-{{ $sourceBadge['color'] }}-50 dark:bg-{{ $sourceBadge['color'] }}-500/10 px-2 py-1 text-[10px] font-semibold text-{{ $sourceBadge['color'] }}-600 dark:text-{{ $sourceBadge['color'] }}-400 ring-1 ring-inset ring-{{ $sourceBadge['color'] }}-500/20">
                                    {!! $sourceBadge['icon'] !!}
                                    {{ $sourceBadge['label'] }}
                                </span>
                            </div>

                            {{-- Item Info --}}
                            <h4 class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 mb-1 leading-tight group-hover:text-{{ $column['color'] }}-600 dark:group-hover:text-{{ $column['color'] }}-400 transition-colors line-clamp-2">
                                {{ $queue->item->name ?? 'Barang Dihapus' }}
                            </h4>
                            
                            {{-- Vendor Info --}}
                            @if(in_array($statusKey, ['ordered', 'completed', 'archived']) && $queue->fulfillments->count() > 0)
                                @php
                                    $vendorName = $queue->fulfillments->first()->purchaseOrderItem->purchaseOrder->vendor->name ?? null;
                                @endphp
                                @if($vendorName)
                                    <div class="mt-1.5 flex items-center gap-1.5 text-[11px] text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50 w-max max-w-full px-2 py-1 rounded-md border border-zinc-100 dark:border-zinc-700/50">
                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        <span class="font-medium truncate">{{ $vendorName }}</span>
                                    </div>
                                @endif
                            @endif

                            {{-- Qty Bottom --}}
                            <div class="flex items-end justify-between text-sm mt-3 pt-3 border-t border-dashed border-zinc-200 dark:border-zinc-700">
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-0.5">Jumlah Diperlukan</span>
                                    <span class="text-lg font-black text-zinc-800 dark:text-zinc-200 tracking-tight leading-none">{{ $queue->requested_qty }} <span class="text-xs font-medium text-zinc-500">Unit</span></span>
                                </div>
                                @if($queue->approved_qty !== null && $queue->status !== 'rejected')
                                    @if($queue->approved_qty < $queue->requested_qty)
                                        <div class="flex flex-col text-right" title="Hanya disetujui sebagian">
                                            <span class="text-[10px] font-bold text-amber-500 uppercase tracking-wider mb-0.5">Disetujui</span>
                                            <span class="text-sm font-bold text-amber-600 dark:text-amber-400 tracking-tight leading-none">{{ $queue->approved_qty }} Unit</span>
                                        </div>
                                    @elseif($queue->approved_qty > $queue->requested_qty)
                                        <div class="flex flex-col text-right" title="Disetujui lebih dari permintaan">
                                            <span class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-0.5">Disetujui Ekstra</span>
                                            <span class="text-sm font-bold text-blue-600 dark:text-blue-400 tracking-tight leading-none">+{{ $queue->approved_qty }} Unit</span>
                                        </div>
                                    @endif
                                @endif
                                @if($queue->status === 'rejected')
                                    <div class="flex flex-col text-right">
                                        <span class="text-[10px] font-bold text-red-500 uppercase tracking-wider mb-0.5">Disetujui</span>
                                        <span class="text-sm font-bold text-red-600 dark:text-red-400 tracking-tight leading-none">0 Unit</span>
                                    </div>
                                @endif
                            </div>


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
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama atau kode barang..." class="w-full" />
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
                    </div>
                </div>
            </div>
        </div>

        <div class="px-2 sm:px-6">
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm mb-6">
                <div class="overflow-x-auto min-h-[50vh]">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$sortBy === 'id'" :direction="$sortDirection" wire:click="sort('id')">No. Antrean & Tanggal</flux:table.column>
                            <flux:table.column>Barang</flux:table.column>
                            <flux:table.column>Sumber & Vendor</flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'requested_qty'" :direction="$sortDirection" wire:click="sort('requested_qty')">Qty</flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
                            <flux:table.column>Aksi</flux:table.column>
                        </flux:table.columns>
        
                        <flux:table.rows>
                            @forelse($this->tableQueues as $queue)
                                @php
                                    $sourceBadge = match($queue->source_type) {
                                        'low_stock' => [
                                            'color' => 'red',
                                            'icon' => '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
                                            'label' => 'Stok Menipis'
                                        ],
                                        'sales' => [
                                            'color' => 'emerald',
                                            'icon' => '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>',
                                            'label' => 'Pesanan Jual'
                                        ],
                                        default => [
                                            'color' => 'zinc',
                                            'icon' => '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>',
                                            'label' => 'Manual'
                                        ]
                                    };
                                @endphp
                                <flux:table.row :key="$queue->id" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50 transition-colors">
                                    <flux:table.cell>
                                        <div class="pl-2 sm:pl-4">
                                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">#ANT-{{ str_pad($queue->id, 4, '0', STR_PAD_LEFT) }}</div>
                                            <div class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($queue->created_at)->format('d M Y') }}</div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-md bg-zinc-100 overflow-hidden border border-zinc-200 shrink-0">
                                                @if ($queue->item?->image)
                                                    <img src="{{ asset('storage/' . $queue->item->image) }}" loading="lazy" class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                                        <flux:icon.photo class="w-5 h-5" />
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100 line-clamp-1">{{ $queue->item->name ?? 'Barang Dihapus' }}</div>
                                                <div class="text-[11px] text-zinc-500 flex items-center gap-1 mt-0.5">
                                                    {{ $queue->item->code ?? '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div>
                                            <span class="inline-flex items-center rounded-md bg-{{ $sourceBadge['color'] }}-50 dark:bg-{{ $sourceBadge['color'] }}-500/10 px-2 py-0.5 text-[10px] font-semibold text-{{ $sourceBadge['color'] }}-600 dark:text-{{ $sourceBadge['color'] }}-400 ring-1 ring-inset ring-{{ $sourceBadge['color'] }}-500/20 mb-1">
                                                {!! $sourceBadge['icon'] !!}
                                                {{ $sourceBadge['label'] }}
                                            </span>
                                            
                                            @if(in_array($queue->status, ['ordered', 'completed', 'archived']) && $queue->fulfillments->count() > 0)
                                                @php
                                                    $vendorName = $queue->fulfillments->first()->purchaseOrderItem->purchaseOrder->vendor->name ?? null;
                                                @endphp
                                                @if($vendorName)
                                                    <div class="flex items-center gap-1 text-[11px] text-zinc-500">
                                                        <flux:icon.building-storefront class="w-3 h-3" />
                                                        <span class="truncate">{{ $vendorName }}</span>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="flex flex-col">
                                            <div class="text-sm">Diminta: <span class="font-bold">{{ $queue->requested_qty }}</span></div>
                                            @if($queue->approved_qty !== null && $queue->status !== 'rejected')
                                                <div class="text-sm">Disetujui: <span class="font-bold text-{{ $queue->approved_qty < $queue->requested_qty ? 'amber' : ($queue->approved_qty > $queue->requested_qty ? 'blue' : 'emerald') }}-600">{{ $queue->approved_qty }}</span></div>
                                            @endif
                                            @if($queue->status === 'rejected')
                                                <div class="text-sm">Disetujui: <span class="font-bold text-red-600">0</span></div>
                                            @endif
                                        </div>
                                    </flux:table.cell>

                                    <flux:table.cell>
                                        @if(array_key_exists($queue->status, $columns))
                                            @php $col = $columns[$queue->status]; @endphp
                                            <flux:badge size="sm" color="{{ $col['color'] }}">{{ $col['title'] }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc">{{ $queue->status }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="flex items-center justify-end gap-2 pr-2 sm:pr-4">
                                            @if($queue->status === 'approved')
                                                @can('purchase.queue.update')
                                                    <flux:button size="sm" variant="subtle" icon="x-mark" class="h-8 p-2 text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/50" title="Tolak Permintaan" wire:click.stop="rejectQueue({{ $queue->id }})" />
                                                @endcan
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="6">
                                        <div class="flex flex-col items-center justify-center py-12 text-zinc-500">
                                            <flux:icon.inbox class="w-12 h-12 mb-3 text-zinc-300" />
                                            <p>Tidak ada antrean pembelian.</p>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
            <x-load-more :paginator="$this->tableQueues" item-name="Antrean" />
        </div>
    </div>
    

    <livewire:queue.consolidation-modal />
    <livewire:global.item-gallery-modal context="purchase" />
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
