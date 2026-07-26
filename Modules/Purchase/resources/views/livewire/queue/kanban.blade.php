<?php
use function Livewire\Volt\{state, layout, title, computed, on, mount, usesPagination};
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
    'columnLimits' => [],
]);

mount(function () {
    $limits = [];
    foreach ($this->columns as $key => $col) {
        $limits[$key] = 10;
    }
    $this->columnLimits = $limits;
});

$loadMoreColumn = function ($status) {
    $limits = $this->columnLimits;
    if (!isset($limits[$status])) {
        $limits[$status] = 10;
    }
    $limits[$status] += 15;
    $this->columnLimits = $limits;
};

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

$getBaseQuery = function () {
    $query = PurchaseQueue::with(['item', 'fulfillments.purchaseOrderItem.purchaseOrder.vendor'])->latest();
    if ($this->search) {
        $query->whereHas('item', function($q) {
            $q->where('name', 'like', '%' . $this->search . '%')
              ->orWhere('code', 'like', '%' . $this->search . '%');
        });
    }
    return $query;
};

$queues = computed(function () {
    if ($this->viewMode !== 'kanban') return collect();
    
    $ids = [];
    foreach ($this->columns as $status => $col) {
        $limit = $this->columnLimits[$status] ?? 10;
        
        $query = clone $this->getBaseQuery();
        
        $statusIds = $query->where('status', $status)
                           ->limit($limit)
                           ->pluck('id')
                           ->toArray();
                           
        $ids = array_merge($ids, $statusIds);
    }
    
    if (empty($ids)) return collect();
    
    $result = PurchaseQueue::with(['item', 'fulfillments.purchaseOrderItem.purchaseOrder.vendor'])
        ->whereIn('id', $ids)
        ->get()
        ->sortBy(function($queue) use ($ids) {
            return array_search($queue->id, $ids);
        });
        
    return $result->groupBy(function($q) {
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
    
    // Simpan daftar queue ke cache dengan one-time token (berlaku 5 menit)
    $token = \Illuminate\Support\Str::random(40);
    \Illuminate\Support\Facades\Cache::put('pq_create_' . $token, $this->selectedQueues, now()->addMinutes(5));
    
    return $this->redirectRoute('purchase.orders.create', ['token' => $token], navigate: true);
};

on(['status-updated' => function () {
    // Kosong saja, tujuannya hanya memancing re-render agar computed $queues dijalankan ulang
}]);

on(['echo:kanban.purchase_queue,KanbanUpdated' => '$refresh']);

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
                    <button type="button" wire:key="kanban-sw-kanban" @click="$wire.setViewMode('kanban')" class="p-1 sm:p-1.5 px-2 sm:px-2.5 transition-colors {{ $this->viewMode === 'kanban' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Kanban">
                        <flux:icon.view-columns wire:loading.remove wire:target="setViewMode('kanban')" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('kanban')" class="w-3.5 h-3.5 sm:w-4 sm:h-4 animate-spin" />
                    </button>
                    <button type="button" wire:key="kanban-sw-table" @click="$wire.setViewMode('table')" class="p-1 sm:p-1.5 px-2 sm:px-2.5 transition-colors {{ $this->viewMode === 'table' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Tabel">
                        <flux:icon.table-cells wire:loading.remove wire:target="setViewMode('table')" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('table')" class="w-3.5 h-3.5 sm:w-4 sm:h-4 animate-spin" />
                    </button>
                </div>
            </x-slot:actions>
        @foreach($columns as $statusKey => $column)
            @php
                $defaultCollapsed = in_array($statusKey, ['archived', 'rejected']);
            @endphp
            <x-kanban.column 
                :statusKey="$statusKey" 
                :column="$column" 
                :componentId="'queue'" 
                :count="count($this->queues[$statusKey] ?? [])"
                :defaultCollapsed="$defaultCollapsed"
            >
                        @if($statusKey === 'approved')
                            <div x-show="$wire.selectedQueues.length > 0 && !collapsed" x-transition.opacity.duration.200ms style="display:none" class="mb-3">
                                <flux:button size="sm" variant="primary" wire:click="proceedToPO" x-on:click="document.dispatchEvent(new Event('livewire:navigate'))" wire:loading.attr="disabled" class="w-full !rounded-xl shadow-sm hover:shadow-md transition-all group overflow-hidden">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <flux:icon.document-plus wire:loading.remove wire:target="proceedToPO" class="size-4 transition-transform group-hover:-translate-y-0.5" />
                                        <flux:icon.arrow-path wire:loading wire:target="proceedToPO" class="size-4 animate-spin" />
                                        <span class="font-medium text-xs uppercase tracking-wider" x-text="'Buat PO (' + $wire.selectedQueues.length + ')'"></span>
                                    </div>
                                </flux:button>
                            </div>
                        @endif
                        @forelse($this->queues[$statusKey] ?? [] as $queue)
                        @php
                            $isCustom = str_contains($queue->notes ?? '', '[CUSTOM]');
                            $finalQty = $queue->status === 'rejected' ? 0 : ($queue->approved_qty ?? $queue->requested_qty);
                            $po = null;
                            $vendorName = null;
                            $poNumber = null;
                            
                            if(in_array($statusKey, ['ordered', 'completed', 'archived']) && $queue->fulfillments->count() > 0) {
                                $poItem = $queue->fulfillments->first()->purchaseOrderItem ?? null;
                                $po = $poItem->purchaseOrder ?? null;
                                if($po) {
                                    $vendorName = $po->vendor->name ?? null;
                                    $poNumber = $po->po_number ?? null;
                                }
                            }
                            
                            $cardClasses = $isCustom 
                                ? "bg-white dark:bg-zinc-800 p-2 rounded-lg shadow-sm border border-amber-400 dark:border-amber-500 shadow-amber-500/20 hover:shadow-lg hover:shadow-amber-500/30 hover:-translate-y-1 active:scale-[0.98] transition-all duration-700 cursor-pointer group relative flex flex-col gap-1.5"
                                : "bg-white dark:bg-zinc-800 p-2 rounded-lg shadow-sm border-l-4 border-l-" . $column['color'] . "-500 border-y border-r border-zinc-200 dark:border-zinc-700 hover:shadow-lg hover:-translate-y-1 hover:border-r-" . $column['color'] . "-300 dark:hover:border-r-" . $column['color'] . "-500/50 active:scale-[0.98] transition-all duration-700 cursor-pointer group relative flex flex-col gap-1.5";
                        @endphp
                        
                        <div wire:key="queue-{{ $queue->id }}" 
                             x-data="{ showDetails: false }"
                             @click="
                                 if ($event.target.closest('[data-checkbox-area]')) return;
                                 if (window.matchMedia('(hover: hover)').matches) {
                                     activeId = '{{ $queue->id }}';
                                 } else {
                                     if (!showDetails) {
                                         showDetails = true;
                                     } else {
                                         activeId = '{{ $queue->id }}';
                                     }
                                 }
                             "
                             @click.outside="showDetails = false"
                             x-show="processingId !== '{{ $queue->id }}'"
                             class="{{ $cardClasses }}">
                            
                            {{-- Row 1: Header (ID, Custom Badge, Time) --}}
                            <div class="flex justify-between items-center relative z-10">
                                <div class="flex items-center gap-1.5">
                                    @if($statusKey === 'approved')
                                        <div data-checkbox-area @click.stop class="flex items-center">
                                            <flux:checkbox wire:model="selectedQueues" value="{{ $queue->id }}" />
                                        </div>
                                    @endif
                                    <span class="text-[10px] sm:text-[11px] font-bold font-mono text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 rounded shrink-0">#ANT-{{ str_pad($queue->id, 4, '0', STR_PAD_LEFT) }}</span>
                                    @if($isCustom)
                                        <span class="text-[9px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-500 bg-amber-100 dark:bg-amber-500/20 px-1.5 py-0.5 rounded shadow-sm">CUSTOM</span>
                                    @endif
                                </div>
                                <span class="text-[9px] text-zinc-400 font-medium shrink-0" title="{{ $queue->created_at->translatedFormat('d F Y H:i') }}">
                                    {{ str_replace('yang ', '', $queue->created_at->locale('id')->diffForHumans()) }}
                                </span>
                            </div>
                            
                            {{-- Row 2: Main Info (Image, Name, Qty) & PO Details --}}
                            <div class="flex items-center justify-between gap-2 overflow-hidden relative z-10">
                                <div class="flex items-center gap-2 overflow-hidden min-w-0">
                                    @if($queue->item?->image)
                                        <button type="button" @click.stop="$dispatch('open-lightbox', { url: '{{ asset('storage/' . $queue->item->image) }}' })" class="w-10 h-10 rounded-md bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 overflow-hidden shrink-0 hover:opacity-80 transition-opacity cursor-zoom-in focus:outline-none" title="Lihat Foto Barang">
                                            <img src="{{ asset('storage/' . $queue->item->image) }}" class="w-full h-full object-cover">
                                        </button>
                                    @endif
                                    <div class="flex flex-col min-w-0">
                                        <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100 leading-tight truncate group-hover:text-{{ $column['color'] }}-500 transition-colors" title="{{ $queue->item->name ?? 'Barang Dihapus' }}">
                                            {{ $queue->item->name ?? 'Barang Dihapus' }}
                                        </span>
                                        <div class="flex flex-wrap items-center gap-x-1.5 mt-0.5">
                                            <span class="text-[9px] font-mono text-zinc-500">{{ $queue->item->code ?? '-' }}</span>
                                            <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                            <div class="flex items-baseline gap-0.5 shrink-0">
                                                <span class="text-xs font-black text-emerald-600 dark:text-emerald-500">{{ $finalQty }}</span>
                                                <span class="text-[9px] font-bold text-zinc-500 uppercase">{{ $queue->item->unit->name ?? 'Unit' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                @if($po)
                                    <div class="flex flex-col items-end shrink-0 pl-2 text-right">
                                        <span class="font-mono text-[9px] text-blue-600 dark:text-blue-400 font-bold hover:text-blue-500 cursor-pointer transition-colors" title="Lihat Purchase Order">{{ $poNumber }}</span>
                                        <div class="flex items-center gap-1 mt-0.5 text-zinc-500">
                                            <flux:icon.building-storefront class="w-2.5 h-2.5 shrink-0" />
                                            <span class="text-[9px] truncate max-w-[80px]" title="{{ $vendorName }}">{{ $vendorName ?? 'Tanpa Vendor' }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            
                            {{-- Row 3: Notes & Approvals (Hidden until hover) --}}
                            @if($queue->notes || ($queue->approved_qty !== null && $queue->approved_qty != $queue->requested_qty && $queue->status !== 'rejected'))
                                <div :class="showDetails ? 'max-h-32 opacity-100 mt-2 border-zinc-100 dark:border-zinc-800/50 pt-1.5' : ''" class="max-h-0 opacity-0 group-hover:max-h-32 group-hover:opacity-100 group-hover:mt-2 transition-all duration-700 ease-in-out overflow-hidden flex flex-col gap-1.5 relative z-10 border-t border-transparent group-hover:border-zinc-100 dark:group-hover:border-zinc-800/50 group-hover:pt-1.5">
                                    @if($queue->notes)
                                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 leading-snug line-clamp-3 italic text-justify" title="{{ strip_tags(str_replace('[CUSTOM]', '', $queue->notes)) }}" x-html="`{{ str_replace('[CUSTOM]', '', addslashes($queue->notes)) }}`"></div>
                                    @endif
                                    
                                    @if($queue->approved_qty !== null && $queue->approved_qty != $queue->requested_qty && $queue->status !== 'rejected')
                                        <div class="text-[10px] font-medium text-amber-600 dark:text-amber-500 italic">Disetujui sebagian (Req: {{ $queue->requested_qty }})</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400 dark:text-zinc-500">
                            Kosong
                        </div>
                    @endforelse
                    
                    @if(count($this->queues[$statusKey] ?? []) >= ($columnLimits[$statusKey] ?? 10))
                        <x-kanban.load-more :statusKey="$statusKey" />
                    @endif
            </x-kanban.column>
        @endforeach
    </x-kanban.board>
    </div>

    {{-- Table View --}}
    <div wire:key="view-table-wrapper" class="w-full {{ $this->viewMode === 'table' ? 'block' : 'hidden' }}">
        <x-table.header searchModel="search" searchPlaceholder="Cari nama atau kode barang...">
                <div class="flex border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden shrink-0 bg-white dark:bg-zinc-900">
                    <button type="button" wire:key="table-sw-kanban" @click="$wire.setViewMode('kanban')" class="p-1.5 px-3 transition-colors {{ $this->viewMode === 'kanban' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Kanban">
                        <flux:icon.view-columns wire:loading.remove wire:target="setViewMode('kanban')" class="w-4 h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('kanban')" class="w-4 h-4 animate-spin" />
                    </button>
                    <button type="button" wire:key="table-sw-table" @click="$wire.setViewMode('table')" class="p-1.5 px-3 transition-colors {{ $this->viewMode === 'table' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Tabel">
                        <flux:icon.table-cells wire:loading.remove wire:target="setViewMode('table')" class="w-4 h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('table')" class="w-4 h-4 animate-spin" />
                    </button>
                </div>
        </x-table.header>

        <x-table.wrapper>
                    <flux:table class="table-mobile-cards">
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
                                        <div class="pl-2 sm:pl-4 flex items-center gap-2">
                                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100 whitespace-nowrap">#ANT-{{ str_pad($queue->id, 4, '0', STR_PAD_LEFT) }}</div>
                                            <div class="text-xs text-zinc-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($queue->created_at)->format('d M Y') }}</div>
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
                                        <div class="flex items-center gap-3">
                                            <div class="text-sm">Diminta: <span class="font-bold">{{ $queue->requested_qty }}</span></div>
                                            @if($queue->approved_qty !== null && $queue->status !== 'rejected')
                                                <div class="text-sm">Disetujui: <span class="font-bold text-{{ $queue->approved_qty < $queue->requested_qty ? 'amber' : ($queue->approved_qty > $queue->requested_qty ? 'blue' : 'emerald') }}-600">{{ $queue->approved_qty }}</span></div>
                                            @endif
                                            @if($queue->status === 'rejected')
                                                <div class="text-sm">Disetujui: <span class="font-bold text-red-600">0</span></div>
                                            @endif
                                        </div>
                                    </flux:table.cell>

                                    <flux:table.cell class="card-status-overlay">
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
        </x-table.wrapper>
        
        <div class="px-0 sm:px-4 lg:px-6 pb-24">
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
