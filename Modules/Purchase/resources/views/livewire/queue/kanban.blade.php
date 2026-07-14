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
        'approved' => ['title' => 'Antre', 'color' => 'blue'],
        'ordered' => ['title' => 'Sudah Dipesan (PO)', 'color' => 'amber'],
        'completed' => ['title' => 'Selesai', 'color' => 'emerald'],
        'rejected' => ['title' => 'Ditolak', 'color' => 'red'],
        'archived' => ['title' => 'Arsip', 'color' => 'slate'],
    ],
    'transparent_columns' => false,
    'search' => '',
    'selectedQueues' => [],
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

$toggleSelection = function ($queueId) {
    if (in_array($queueId, $this->selectedQueues)) {
        $this->selectedQueues = array_diff($this->selectedQueues, [$queueId]);
    } else {
        $this->selectedQueues[] = $queueId;
    }
};

$proceedToPO = function () {
    if (empty($this->selectedQueues)) {
        \Flux::toast('Pilih minimal satu antrean untuk dibuatkan PO.', variant: 'danger');
        $this->dispatch('hide-global-loader');
        return;
    }
    
    // Redirect ke halaman Create PO dengan membawa parameter queues
    $queueIds = implode(',', $this->selectedQueues);
    return $this->redirectRoute('purchase.orders.create', ['queues' => $queueIds], navigate: true);
};

on(['status-updated' => function () {
    // Kosong saja, tujuannya hanya memancing re-render agar computed $queues dijalankan ulang
}]);

?>

<div class="w-full bg-transparent relative" x-data="{
    init() {
        Livewire.on('hide-global-loader', () => {
            document.dispatchEvent(new Event('livewire:navigate-end'));
        });
    }
}">

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
                        @if($statusKey === 'approved' && count($this->selectedQueues) > 0)
                            <div x-show="!collapsed">
                                <flux:button size="sm" variant="primary" wire:click="proceedToPO" x-on:click="document.dispatchEvent(new Event('livewire:navigate'))" wire:loading.attr="disabled" class="!rounded-full shadow-sm hover:shadow-md transition-all group overflow-hidden">
                                    <div class="flex items-center gap-1.5">
                                        <flux:icon.document-plus wire:loading.remove wire:target="proceedToPO" class="size-4 transition-transform group-hover:-translate-y-0.5" />
                                        <flux:icon.arrow-path wire:loading wire:target="proceedToPO" class="size-4 animate-spin" />
                                        <span class="font-medium text-xs uppercase tracking-wider">Buat PO ({{ count($this->selectedQueues) }})</span>
                                    </div>
                                </flux:button>
                            </div>
                        @endif
                        <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800 shrink-0">{{ count($this->queues[$statusKey] ?? []) }}</flux:badge>
                        <flux:button size="sm" variant="subtle" class="!px-1.5 !py-1.5 shrink-0" @click.stop="collapsed = !collapsed" x-bind:title="collapsed ? 'Buka Kolom' : 'Tutup Kolom'">
                            <flux:icon.arrows-up-down x-show="collapsed" class="w-4 h-4" />
                            <flux:icon.arrows-right-left x-show="!collapsed" class="w-4 h-4" />
                        </flux:button>
                    </div>
                </div>

                {{-- Column Items --}}
                <div x-show="!collapsed" x-transition.opacity.duration.300ms x-animate class="flex-1 p-3 overflow-y-auto space-y-3" :class="transparent ? 'hide-scroll' : 'custom-scrollbar'">
                        @forelse($this->queues[$statusKey] ?? [] as $queue)
                        @php
                            $isCustom = str_contains($queue->notes ?? '', '[CUSTOM]');
                        @endphp
                        <div wire:key="queue-{{ $queue->id }}" 
                             @click="activeId = '{{ $queue->id }}'"
                             x-show="processingId !== '{{ $queue->id }}'"
                             class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border transition-all duration-300 group relative flex flex-col overflow-hidden {{ $isCustom ? 'border-amber-400 dark:border-amber-500 shadow-amber-500/20 hover:-translate-y-1 hover:shadow-lg hover:shadow-amber-500/30 hover:border-amber-500' : 'border-zinc-200 dark:border-zinc-700 hover:-translate-y-1 hover:shadow-md hover:border-' . $column['color'] . '-400 dark:hover:border-' . $column['color'] . '-500' }}">
                            
                            @if($isCustom)
                                <div class="absolute top-0 right-0 w-24 h-24 pointer-events-none opacity-40 dark:opacity-20">
                                    <div class="absolute inset-0 bg-gradient-to-bl from-amber-400 to-transparent"></div>
                                </div>
                            @endif
                            
                            {{-- Header Card: Checkbox & ID, and Time --}}
                            <div class="flex justify-between items-center mb-2 relative z-10">
                                <div class="flex items-center gap-2">
                                    @if($statusKey === 'approved')
                                        <flux:checkbox wire:click.stop="toggleSelection({{ $queue->id }})" :checked="in_array($queue->id, $this->selectedQueues)" />
                                    @endif
                                    <span class="font-mono text-[11px] font-bold text-zinc-600 dark:text-zinc-300">#ANT-{{ str_pad($queue->id, 4, '0', STR_PAD_LEFT) }}</span>
                                    @if($isCustom)
                                        <span class="text-[9px] font-black uppercase tracking-widest text-white bg-gradient-to-r from-amber-500 to-orange-500 px-1.5 py-0.5 rounded shadow-sm shadow-amber-500/40" title="Permintaan dengan spesifikasi khusus">
                                            Custom
                                        </span>
                                    @endif
                                </div>
                                <span class="text-[10px] text-zinc-400 font-medium">{{ $queue->created_at->diffForHumans(null, true, true) }}</span>
                            </div>
                            
                            {{-- Main Info: Image & Item --}}
                            <div class="flex gap-3 relative z-10">
                                {{-- Item Image --}}
                                <div class="w-10 h-10 rounded-md bg-zinc-100 dark:bg-zinc-800 overflow-hidden shrink-0 border border-zinc-200 dark:border-zinc-700">
                                    @if($queue->item?->image)
                                        <img src="{{ asset('storage/' . $queue->item->image) }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                            <flux:icon.photo class="w-5 h-5" />
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="flex-1 min-w-0 flex flex-col justify-center">
                                    <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100 leading-tight truncate group-hover:text-{{ $column['color'] }}-600 transition-colors">
                                        {{ $queue->item->name ?? 'Barang Dihapus' }}
                                    </h4>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[10px] font-mono text-zinc-500">{{ $queue->item->code ?? '-' }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Notes if any --}}
                            @if($queue->notes)
                                <div class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50 p-1.5 rounded border border-zinc-100 dark:border-zinc-700/50 line-clamp-2 italic relative z-10">
                                    <span class="line-clamp-2 leading-relaxed prose prose-xs" x-html="`{{ str_replace('[CUSTOM]', '', addslashes($queue->notes)) }}`"></span>
                                </div>
                            @endif
                            
                            {{-- PO & Vendor Info --}}
                            @if(in_array($statusKey, ['ordered', 'completed', 'archived']) && $queue->fulfillments->count() > 0)
                                @php
                                    $poItem = $queue->fulfillments->first()->purchaseOrderItem ?? null;
                                    $po = $poItem->purchaseOrder ?? null;
                                    $vendorName = $po->vendor->name ?? null;
                                    $poNumber = $po->po_number ?? null;
                                    
                                    $isPartiallyReceived = $poItem && $poItem->received_quantity > 0 && $poItem->received_quantity < $poItem->quantity;
                                @endphp
                                @if($po)
                                    <div class="mt-2 flex flex-col gap-1.5 text-[10px] bg-zinc-50 dark:bg-zinc-800/50 p-2 rounded border border-zinc-100 dark:border-zinc-700/50 relative z-10">
                                        <div class="flex items-center justify-between gap-2 text-zinc-500 dark:text-zinc-400">
                                            <div class="flex items-center gap-1.5 truncate">
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                                <span class="font-medium truncate">{{ $vendorName ?? 'Tanpa Vendor' }}</span>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0 font-mono text-zinc-600 dark:text-zinc-300">
                                                <flux:icon.document-text class="w-3 h-3 text-blue-500" />
                                                <span class="font-bold hover:text-blue-600 cursor-pointer transition-colors" title="Lihat Purchase Order">{{ $poNumber }}</span>
                                            </div>
                                        </div>
                                        
                                        @if($isPartiallyReceived)
                                            <div class="pt-1 mt-1 border-t border-zinc-200/60 dark:border-zinc-700/60 flex flex-col gap-1">
                                                <div class="flex justify-between items-center text-[9px] font-bold">
                                                    <span class="text-amber-600 dark:text-amber-500 uppercase tracking-wider">Tiba Sebagian</span>
                                                    <span class="text-zinc-600 dark:text-zinc-300">{{ $poItem->received_quantity }} / {{ $poItem->quantity }}</span>
                                                </div>
                                                <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5 overflow-hidden">
                                                    <div class="bg-amber-500 h-1.5 rounded-full" style="width: {{ ($poItem->received_quantity / max(1, $poItem->quantity)) * 100 }}%"></div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            {{-- Footer: Qty --}}
                            @php
                                $finalQty = $queue->status === 'rejected' ? 0 : ($queue->approved_qty ?? $queue->requested_qty);
                            @endphp
                            <div class="flex items-center justify-between mt-3 pt-2 border-t border-zinc-100 dark:border-zinc-800 relative z-10">
                                <div class="flex items-baseline gap-1 text-zinc-800 dark:text-zinc-200">
                                    <span class="text-sm font-black">{{ $finalQty }}</span>
                                    <span class="text-[10px] font-medium text-zinc-500 uppercase">{{ $queue->item->unit->name ?? 'Unit' }}</span>
                                </div>
                                
                                @if($queue->approved_qty !== null && $queue->approved_qty != $queue->requested_qty && $queue->status !== 'rejected')
                                    <span class="text-[9px] text-zinc-400 italic" title="Awalnya diminta: {{ $queue->requested_qty }}">
                                        (Req: {{ $queue->requested_qty }})
                                    </span>
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
