<?php
use function Livewire\Volt\{state, layout, title, computed, on, usesPagination, mount};
use Modules\Production\Models\ProductionOrder;

usesPagination(theme: 'tailwind');

layout('layouts.app');
title('Kanban Produksi');

state([
    'columns' => [
        'material_fulfillment' => ['title' => 'Pemenuhan Bahan', 'color' => 'orange'],
        'waiting_vendor' => ['title' => 'Antrean Vendor', 'color' => 'cyan'],
        'pending_approval' => ['title' => 'Menunggu Finance', 'color' => 'amber'],
        'in_production' => ['title' => 'Diproses Vendor', 'color' => 'blue'],
        'receiving' => ['title' => 'Penerimaan Gudang', 'color' => 'purple'],
        'completed' => ['title' => 'Selesai', 'color' => 'emerald'],
        'archived' => ['title' => 'Arsip', 'color' => 'zinc'],
    ],
    'search' => '',
    'selectedOrders' => [],
    'viewModeMaklon' => 'grouped', // 'grouped' or 'list'
    'validationErrors' => [],
    'transparent_columns' => false,
    'viewMode' => session('prod_view_mode', 'kanban'),
    'sortBy' => 'created_at',
    'sortDirection' => 'desc',
    'perPage' => 15,
    'columnLimits' => [],
]);


mount(function () {
    $limits = [];
    foreach ($this->columns as $key => $col) {
        $limits[$key] = 24;
    }
    $this->columnLimits = $limits;
});

$loadMoreColumn = function ($status) {
    $limits = $this->columnLimits;
    if (!isset($limits[$status])) {
        $limits[$status] = 24;
    }
    $limits[$status] += 24;
    $this->columnLimits = $limits;
};

$loadMore = function () {
    $this->perPage += 15;
};

$setViewMode = function ($mode) {
    $this->viewMode = $mode;
    session(['prod_view_mode' => $mode]);
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
    $query = ProductionOrder::query();
    if ($this->search) {
        $query->where('order_number', 'like', '%' . $this->search . '%')
              ->orWhere('reference_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('item', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    return $query;
};

$orders = computed(function () {
    if ($this->viewMode !== 'kanban') return collect();
    
    $ids = [];
    foreach ($this->columns as $status => $col) {
        $limit = $this->columnLimits[$status] ?? 24;
        $q = clone $this->getBaseQuery();
        
        if ($status === 'material_fulfillment') {
            $q->whereIn('status', ['material_fulfillment', 'waiting_material', 'material_issued']);
        } else {
            $q->where('status', $status);
        }
        
        $ids = array_merge($ids, $q->latest()->limit($limit)->pluck('id')->toArray());
    }
    
    if (empty($ids)) return collect();
    
    $result = ProductionOrder::with(['item', 'creator'])
        ->whereIn('id', $ids)
        ->get()
        ->sortByDesc('created_at');
        
    $grouped = $result->groupBy(function($order) {
        if (in_array($order->status, ['waiting_material', 'material_issued'])) {
            return 'material_fulfillment';
        }
        return $order->status;
    });
    
    return $grouped;
});

$tableOrders = computed(function () {
    $query = ProductionOrder::with(['item', 'creator', 'purchaseOrder.vendor'])
        ->select('production_orders.*');
        
    if ($this->search) {
        $query->where('order_number', 'like', '%' . $this->search . '%')
              ->orWhere('reference_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('item', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    
    $query->orderBy($this->sortBy, $this->sortDirection);
    
    return $query->paginate($this->perPage);
});

on([
    'status-updated' => function () {
        // Trigger re-render
    },
    'echo:kanban.production_order,KanbanUpdated' => function () {}
]);

$updateStatus = function ($orderId, $newStatus) {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $po = ProductionOrder::find($orderId);
    if ($po) {
        $po->status = $newStatus;
        $po->save();
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        \Flux::toast('Status berhasil diperbarui.', variant: 'success');
    }
};







$toggleSelection = function ($orderId) {
    if (in_array($orderId, $this->selectedOrders)) {
        $this->selectedOrders = array_diff($this->selectedOrders, [$orderId]);
    } else {
        $this->selectedOrders[] = $orderId;
    }
};

$openMaklonModal = function () {
    // Broadcast event secara global untuk memastikan pasti diterima oleh modal
    $this->dispatch('open-maklon-modal', orderIds: array_values($this->selectedOrders));
};

on(['maklon-po-created' => function () {
    $this->selectedOrders = [];
}]);
?>

<div class="w-full bg-transparent relative">
    <div wire:key="view-kanban-wrapper" class="w-full h-full relative {{ $this->viewMode === 'kanban' ? 'flex flex-col' : 'hidden' }}">
        <x-kanban.board 
            componentId="production-order"
            searchModel="search"
            searchPlaceholder="Cari SPK atau produk...">
            
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
                <div class="w-px h-6 bg-zinc-200 dark:bg-zinc-700 mx-1 sm:mx-2 hidden sm:block"></div>
            </x-slot:actions>
        @foreach($columns as $statusKey => $column)
            @php
                $defaultCollapsed = in_array($statusKey, ['completed', 'archived']);
            @endphp
            <x-kanban.column 
                :statusKey="$statusKey" 
                :column="$column" 
                :componentId="'prod'" 
                :count="count($this->orders[$statusKey] ?? [])"
                :defaultCollapsed="$defaultCollapsed"
            >
                <x-slot:headerActions>
                    @if($statusKey === 'waiting_vendor' && count($this->selectedOrders) > 0)
                        <flux:button size="xs" variant="primary" icon="plus" wire:click="openMaklonModal" class="h-6 text-[10px] px-2 rounded-md">
                            Buat SPK ({{ count($this->selectedOrders) }})
                        </flux:button>
                    @endif
                    @if($statusKey === 'in_production' || $statusKey === 'pending_approval')
                        <div class="flex bg-zinc-200/80 dark:bg-zinc-800 p-0.5 rounded gap-0.5 border border-zinc-300 dark:border-zinc-700">
                            <button wire:click="$set('viewModeMaklon', 'grouped')" class="flex items-center justify-center p-1 rounded-sm text-[10px] {{ $viewModeMaklon === 'grouped' ? 'bg-white dark:bg-zinc-600 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}" title="Wadah">
                                <flux:icon.rectangle-group class="w-3 h-3" />
                            </button>
                            <button wire:click="$set('viewModeMaklon', 'list')" class="flex items-center justify-center p-1 rounded-sm text-[10px] {{ $viewModeMaklon === 'list' ? 'bg-white dark:bg-zinc-600 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}" title="Eceran">
                                <flux:icon.list-bullet class="w-3 h-3" />
                            </button>
                        </div>
                    @endif
                </x-slot:headerActions>

                    @if(in_array($statusKey, ['in_production', 'pending_approval']) && $viewModeMaklon === 'grouped')
                        @php
                            $groupedByPo = collect($this->orders[$statusKey] ?? [])->groupBy('purchase_order_id');
                        @endphp
                        
                        @forelse($groupedByPo as $poId => $poOrders)
                            @php $po = $poOrders->first()->purchaseOrder; @endphp
                            <div wire:key="po-group-{{ $statusKey }}-{{ $poId }}" 
                                 x-show="processingId !== 'po-{{ $poId }}'"
                                 class="bg-zinc-100 dark:bg-zinc-800/40 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700">
                                <!-- Group Header -->
                                <div class="bg-white/80 dark:bg-zinc-900/80 p-3 border-b border-blue-100 dark:border-blue-900/50 flex flex-col gap-2 sticky top-0 z-10 backdrop-blur-sm">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <flux:icon.truck class="w-4 h-4 text-blue-500" />
                                            @if($po)
                                                <button type="button" wire:click="$dispatch('open-po-detail-modal', { poId: {{ $po->id }} })" class="font-bold text-sm text-zinc-900 dark:text-zinc-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors flex items-center gap-1 group" title="Buka Detail SPK">
                                                    {{ $po->po_number }}
                                                    <flux:icon.arrow-top-right-on-square class="w-3.5 h-3.5 text-indigo-500 opacity-50 group-hover:opacity-100 transition-opacity" />
                                                </button>
                                            @else
                                                <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Tanpa SPK</span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] text-zinc-500 line-clamp-1 flex items-center gap-1">
                                            <flux:icon.building-storefront class="w-3 h-3" />
                                            {{ $po->vendor->name ?? 'Vendor Internal' }} - Rp {{ number_format($po->total_amount ?? 0, 0, ',', '.') }}
                                        </div>
                                    </div>
                                    <div class="text-right flex flex-col items-end gap-1 shrink-0">
                                        <span class="text-[10px] bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 px-1.5 py-0.5 rounded font-semibold">{{ $poOrders->count() }} Barang</span>
                                        <span class="text-[9px] text-zinc-500">{{ $po->order_date ? \Carbon\Carbon::parse($po->order_date)->format('d M') : '' }}</span>
                                    </div>
                                </div>
                                @if($statusKey === 'in_production')
                                <flux:button size="xs" variant="primary" class="w-full justify-center mt-1" 
                                             @click="activeId = 'po-{{ $poId }}'"
                                             wire:click="$dispatch('open-finish-phase-bulk-modal', { poId: {{ $poId }}, phase: 'maklon' })">
                                    &#x2714; Selesaikan Semua (1 SPK)
                                </flux:button>
                                @elseif($statusKey === 'pending_approval')
                                <div class="mt-1 text-center bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 text-[10px] font-bold py-1 rounded border border-amber-200 dark:border-amber-800">
                                    <flux:icon.clock class="w-3 h-3 inline-block" /> Menunggu ACC Finance
                                </div>
                                @endif
                            </div>
                                
                                <div class="p-2 space-y-2">
                                    @foreach($poOrders as $order)
                                        <div wire:key="card-grouped-{{ $statusKey }}-{{ $order->id }}">
                                            @include('production::livewire.work-order.partials.kanban-card', [
                                                'order' => $order, 
                                                'statusKey' => $statusKey, 
                                                'viewModeMaklon' => $viewModeMaklon,
                                                'hideParentPo' => $poOrders->count() === 1 ? $poId : null
                                            ])
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400">
                                Kosong
                            </div>
                        @endforelse
                    @else
                        @forelse($this->orders[$statusKey] ?? [] as $order)
                            <div wire:key="card-{{ $statusKey }}-{{ $order->id }}">
                                @include('production::livewire.work-order.partials.kanban-card', ['order' => $order, 'statusKey' => $statusKey, 'viewModeMaklon' => $viewModeMaklon])
                            </div>
                        @empty
                            <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400">
                                Kosong
                            </div>
                        @endforelse
                    @endif
            </x-kanban.column>
            @endforeach
    </x-kanban.board>
    </div>

    {{-- Table View --}}
    <div wire:key="view-table-wrapper" class="w-full {{ $this->viewMode === 'table' ? 'block' : 'hidden' }}">
        <x-table.header searchModel="search" searchPlaceholder="Cari SPK atau produk...">
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
                
                <div class="w-px h-6 bg-zinc-200 dark:bg-zinc-700 shrink-0 mx-0.5 sm:mx-2 hidden sm:block"></div>
        </x-table.header>

        <x-table.wrapper>
                <div class="overflow-x-auto min-h-[50vh]">
                    <flux:table class="table-mobile-cards">
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$sortBy === 'order_number'" :direction="$sortDirection" wire:click="sort('order_number')">No. SPK / Ref</flux:table.column>
                            <flux:table.column>Barang</flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Tanggal & Pembuat</flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
                            <flux:table.column>Aksi</flux:table.column>
                        </flux:table.columns>
        
                        <flux:table.rows>
                            @forelse($this->tableOrders as $order)
                                <flux:table.row :key="$order->id" class="cursor-pointer hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50 transition-colors" x-on:click="$dispatch('open-prod-detail-modal', { orderId: {{ $order->id }} })">
                                    <flux:table.cell>
                                        <div class="pl-2 sm:pl-4 flex items-center gap-2">
                                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100 whitespace-nowrap">{{ $order->order_number }}</div>
                                            <div class="text-xs text-zinc-500 whitespace-nowrap">Ref: {{ $order->reference_number ?? '-' }}</div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="flex items-start gap-3 mt-1">
                                            <div class="w-10 h-10 rounded-md bg-zinc-100 overflow-hidden border border-zinc-200 shrink-0">
                                                @if ($order->item?->image)
                                                    <img src="{{ asset('storage/' . $order->item->image) }}" loading="lazy" class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                                        <flux:icon.photo class="w-5 h-5" />
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100 line-clamp-1">{{ $order->item->name ?? 'Barang Dihapus' }}</div>
                                                <div class="text-[11px] text-zinc-500 flex items-center gap-1 mt-0.5">
                                                    Target: <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ $order->target_qty }} Unit</span>
                                                </div>
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="text-sm text-zinc-900 dark:text-zinc-100">{{ \Carbon\Carbon::parse($order->created_at)->format('d M Y') }}</div>
                                        <div class="flex items-center gap-1.5 mt-1 text-[11px] text-zinc-500">
                                            @if($order->creator->avatar ?? false)
                                                <img src="{{ Storage::url($order->creator->avatar) }}" class="w-3.5 h-3.5 rounded-full object-cover" />
                                            @else
                                                <div class="w-3.5 h-3.5 rounded-full bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 flex items-center justify-center text-[8px] font-bold">
                                                    {{ strtoupper(substr($order->creator->name ?? 'A', 0, 1)) }}
                                                </div>
                                            @endif
                                            {{ explode(' ', $order->creator->name ?? '-')[0] }}
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell class="card-status-overlay">
                                        @if(array_key_exists($order->status, $columns))
                                            @php $col = $columns[$order->status]; @endphp
                                            <flux:badge size="sm" color="{{ $col['color'] }}">{{ $col['title'] }}</flux:badge>
                                        @elseif($order->status === 'waiting_material' || $order->status === 'material_issued')
                                            <flux:badge size="sm" color="orange">Pemenuhan Bahan</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc">{{ $order->status }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="flex items-center justify-end gap-2 pr-2 sm:pr-4">
                                            <flux:button size="sm" variant="subtle" icon="document-text" class="h-8 p-2 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/50" title="Detail Produksi" wire:click.stop="$dispatch('open-prod-detail-modal', { orderId: {{ $order->id }} })">Detail</flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5">
                                        <div class="flex flex-col items-center justify-center py-12 text-zinc-500">
                                            <flux:icon.inbox class="w-12 h-12 mb-3 text-zinc-300" />
                                            <p>Tidak ada data produksi.</p>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
        </x-table.wrapper>
        
        <div class="px-0 sm:px-4 lg:px-6 pb-24">
            <x-load-more :paginator="$this->tableOrders" item-name="SPK" />
        </div>
    </div>
    

    {{-- Modals remain unchanged --}}
    <template x-teleport="body">
        <div>
            <livewire:work-order.fulfillment-modal />
            <livewire:work-order.maklon-modal />
            <livewire:work-order.po-detail-modal />
            <livewire:work-order.prod-detail-modal />
            <livewire:work-order.split-order-modal />
            <livewire:work-order.finish-phase-modal />
            <livewire:work-order.vendor-cost-modal />
            <livewire:work-order.po-print-modal />
            <livewire:work-order.material-receipt-modal />
            <livewire:global.vendor-gallery-modal />
            <livewire:global.vendor-form-modal />
            <livewire:work-order.price-history-modal />
            <livewire:global.template-modal />
        </div>
    </template>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
            height: 4px;
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
    </style>
</div>
