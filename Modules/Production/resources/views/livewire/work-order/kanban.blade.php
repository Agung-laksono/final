<?php
use function Livewire\Volt\{state, layout, title, computed, on, usesPagination};
use Modules\Production\Models\ProductionOrder;

usesPagination(theme: 'tailwind');

layout('layouts.app');
title('Kanban Produksi');

state([
    'columns' => [
        'material_fulfillment' => ['title' => 'Pemenuhan Bahan', 'color' => 'orange'],
        'waiting_vendor' => ['title' => 'Antrean Vendor', 'color' => 'cyan'],
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
]);

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

$orders = computed(function () {
    $query = ProductionOrder::with(['item', 'creator'])->latest();
    if ($this->search) {
        $query->where('order_number', 'like', '%' . $this->search . '%')
              ->orWhere('reference_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('item', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    
    $grouped = $query->get()->groupBy('status');
    
    // Merge waiting_material dan material_issued ke dalam material_fulfillment
    $fulfillment = $grouped['material_fulfillment'] ?? collect();
    
    if (isset($grouped['waiting_material'])) {
        $fulfillment = $fulfillment->merge($grouped['waiting_material']);
        unset($grouped['waiting_material']);
    }
    
    if (isset($grouped['material_issued'])) {
        $fulfillment = $fulfillment->merge($grouped['material_issued']);
        unset($grouped['material_issued']);
    }
    
    if ($fulfillment->count() > 0) {
        $grouped['material_fulfillment'] = $fulfillment->sortByDesc('created_at');
    }
    
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

                {{-- Tombol AI Assistant --}}
                <flux:button variant="primary" icon="sparkles" class="px-2.5 sm:px-4 shrink-0" wire:click="$dispatch('open-groq-assistant')">
                    <span class="hidden sm:inline">Tanya AI</span>
                    <span class="sm:hidden">AI</span>
                </flux:button>
            </x-slot:actions>
        @foreach($columns as $statusKey => $column)
            <div x-data="{ collapsed: $persist({{ in_array($statusKey, ['completed', 'archived']) ? 'true' : 'false' }}).as('kanban-col-prod-{{ $statusKey }}-user-{{ auth()->id() }}') }"
                 style="height: 100%; display: flex; flex-direction: column;"
                 class="flex-shrink-0 rounded-xl transition-all duration-300 snap-center"
                 :class="(transparent ? '' : 'bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-800 ') + (collapsed ? 'w-16 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800/80' : 'w-80')"
                 @click="if(collapsed) collapsed = false"
                 wire:key="kanban-column-{{ $statusKey }}">
                 
                <div class="p-4 flex justify-between items-center rounded-t-xl transition-all duration-300"
                     :class="(transparent ? '' : 'bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 ') + (collapsed ? 'flex-col gap-4 h-full pb-8' : '')">
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500 shrink-0"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 transition-all duration-300 whitespace-nowrap"
                            :class="collapsed ? 'vertical-text tracking-widest mt-2' : ''">{{ $column['title'] }}</h3>
                    </div>
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        @if($statusKey === 'waiting_vendor' && count($this->selectedOrders) > 0)
                            <div x-show="!collapsed">
                                <flux:button size="sm" variant="primary" icon="plus" wire:click="$dispatch('open-maklon-modal', { orderIds: {{ json_encode($this->selectedOrders) }} })">Buat SPK</flux:button>
                            </div>
                        @endif
                        @if($statusKey === 'in_production')
                            <div class="flex items-center bg-zinc-100 dark:bg-zinc-800 p-0.5 rounded-lg" x-show="!collapsed">
                                <button wire:click="$set('viewModeMaklon', 'grouped')" class="p-1 rounded-md text-xs {{ $viewModeMaklon === 'grouped' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white font-medium' : 'text-zinc-500 hover:text-zinc-700' }}" title="Mode Wadah">
                                    <flux:icon.rectangle-group class="w-4 h-4" />
                                </button>
                                <flux:button size="sm" variant="subtle" icon="list-bullet" class="!px-1.5 !py-1.5" wire:click="$set('viewModeMaklon', 'list')" x-bind:class="$wire.viewModeMaklon === 'list' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' : 'text-zinc-500'" title="Mode Eceran" />
                            </div>
                        @endif
                        <flux:badge size="sm" class="shrink-0">{{ count($this->orders[$statusKey] ?? []) }}</flux:badge>
                        <flux:button size="sm" variant="subtle" class="!px-1.5 !py-1.5 shrink-0" x-bind:icon="collapsed ? 'arrows-up-down' : 'arrows-right-left'" @click.stop="collapsed = !collapsed" x-bind:title="collapsed ? 'Buka Kolom' : 'Tutup Kolom'" />
                    </div>
                </div>
                
                <div x-show="!collapsed" x-transition.opacity.duration.300ms x-init="autoAnimate($el)" class="flex-1 p-3 overflow-y-auto space-y-3" :class="transparent ? 'hide-scroll' : 'custom-scrollbar'">
                    @if($statusKey === 'in_production' && $viewModeMaklon === 'grouped')
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
                                <flux:button size="xs" variant="primary" class="w-full justify-center mt-1" 
                                             @click="activeId = 'po-{{ $poId }}'"
                                             wire:click="$dispatch('open-finish-phase-bulk-modal', { poId: {{ $poId }}, phase: 'maklon' })">
                                    &#x2714; Selesaikan Semua (1 SPK)
                                </flux:button>
                            </div>
                                
                                <div class="p-2 space-y-2">
                                    @foreach($poOrders as $order)
                                        <div wire:key="card-grouped-{{ $order->id }}">
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
                            <div wire:key="card-{{ $order->id }}">
                                @include('production::livewire.work-order.partials.kanban-card', ['order' => $order, 'statusKey' => $statusKey, 'viewModeMaklon' => $viewModeMaklon])
                            </div>
                        @empty
                            <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400">
                                Kosong
                            </div>
                        @endforelse
                    @endif
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
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari SPK atau produk..." class="w-full" />
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

                        <flux:button variant="primary" icon="sparkles" class="shrink-0" wire:click="$dispatch('open-groq-assistant')">
                            <span class="hidden sm:inline">Tanya AI</span>
                            <span class="sm:hidden">AI</span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-2 sm:px-6">
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm mb-6">
                <div class="overflow-x-auto min-h-[50vh]">
                    <flux:table>
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
                                        <div class="pl-2 sm:pl-4">
                                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $order->order_number }}</div>
                                            <div class="text-xs text-zinc-500">Ref: {{ $order->reference_number ?? '-' }}</div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="flex items-center gap-3">
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
                                        <div class="flex items-center gap-1 mt-1 text-[11px] text-zinc-500">
                                            <flux:icon.user class="w-3 h-3" />
                                            {{ explode(' ', $order->creator->name ?? '-')[0] }}
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
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
                </div>
            </div>
            <x-load-more :paginator="$this->tableOrders" item-name="SPK" />
        </div>
    </div>
    

    {{-- Modals remain unchanged --}}

    <livewire:work-order.fulfillment-modal />
    <livewire:work-order.maklon-modal />
    <livewire:work-order.po-detail-modal />
    <livewire:work-order.prod-detail-modal />
    <livewire:work-order.finish-phase-modal />
    <livewire:work-order.vendor-cost-modal />
    <livewire:work-order.po-print-modal />
    <livewire:work-order.material-receipt-modal />
    {{-- <livewire:work-order.groq-assistant /> --}}
    {{-- <livewire:work-order.claude-assistant /> --}}

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
