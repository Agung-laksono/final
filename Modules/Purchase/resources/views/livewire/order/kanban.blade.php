<?php
use function Livewire\Volt\{state, layout, title, computed, on, mount, usesPagination};
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
        'void' => ['title' => 'Dibatalkan (Void)', 'color' => 'red'],
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
    'columnLimits' => [], // State for per-column limits
]);

mount(function () {
    // Inisialisasi limit per kolom
    $limits = [];
    foreach ($this->columns as $key => $col) {
        $limits[$key] = 24;
    }
    $this->columnLimits = $limits;
});

$loadMore = function () {
    $this->perPage += 15;
};

$loadMoreColumn = function ($status) {
    $limits = $this->columnLimits;
    if (!isset($limits[$status])) {
        $limits[$status] = 24;
    }
    $limits[$status] += 15;
    $this->columnLimits = $limits;
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

$getBaseQuery = function () {
    $query = PurchaseOrder::with(['vendor', 'creator', 'items', 'payments'])
        ->where('po_number', 'not like', 'GUNJAS-%')
        ->latest('updated_at');
    if ($this->search) {
        $query->where(function($q) {
            $q->where('po_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('vendor', function($v) {
                  $v->where('name', 'like', '%' . $this->search . '%');
              });
        });
    }
    return $query;
};

$orders = computed(function () {
    if ($this->viewMode !== 'kanban') return collect();
    
    $ids = [];
    foreach ($this->columns as $status => $col) {
        $limit = $this->columnLimits[$status] ?? 24;
        
        $query = clone $this->getBaseQuery();
        $statusIds = $query->where('status', $status)
                           ->limit($limit)
                           ->pluck('id')
                           ->toArray();
                           
        $ids = array_merge($ids, $statusIds);
    }
    
    if (empty($ids)) return collect();
    
    $result = PurchaseOrder::with(['vendor', 'creator', 'items', 'payments'])
        ->whereIn('id', $ids)
        ->get()
        ->sortBy(function($order) use ($ids) {
            return array_search($order->id, $ids);
        });
        
    return $result->groupBy(function($po) {
        return $po->status ?? 'draft';
    });
});

$tableOrders = computed(function () {
    $query = PurchaseOrder::with(['vendor', 'creator', 'items'])
        ->where('po_number', 'not like', 'GUNJAS-%')
        ->select('purchase_orders.*');
        
    if ($this->search) {
        $query->where(function($q) {
            $q->where('po_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('vendor', function($v) {
                  $v->where('name', 'like', '%' . $this->search . '%');
              });
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
    'echo:kanban,KanbanUpdated' => function () {}
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

                @can('purchase.create')
                    <flux:button variant="primary" size="sm" icon="plus" href="{{ route('purchase.orders.create') }}" class="px-2 sm:px-4 shrink-0" wire:navigate>
                        <span class="hidden sm:inline">Buat PO</span>
                        <span class="sm:hidden text-xs">Buat</span>
                    </flux:button>
                @endcan
            </x-slot:actions>
        @foreach($columns as $statusKey => $column)
            @php
                $defaultCollapsed = $statusKey === 'archived';
            @endphp
            <x-kanban.column 
                :statusKey="$statusKey" 
                :column="$column" 
                :componentId="'order'" 
                :count="count($this->orders[$statusKey] ?? [])"
                :defaultCollapsed="$defaultCollapsed"
            >
                    @forelse($this->orders[$statusKey] ?? [] as $po)
                            @php
                                $isCustom = str_contains($po->notes ?? '', '[CUSTOM]');
                                
                                $totalQty = $po->items->sum('quantity');
                                $receivedQty = $po->items->sum('received_quantity');
                                $receivePercent = $totalQty > 0 ? min(100, round(($receivedQty / $totalQty) * 100)) : 0;
                                
                                $paidAmount = $po->payments ? $po->payments->where('status', 'verified')->sum('amount') : 0;
                                $paymentPercent = $po->total_amount > 0 ? min(100, round(($paidAmount / $po->total_amount) * 100)) : 0;
                                
                                $skuCount = $po->items->count();

                                $deadlineStr = '';
                                $deadlineColor = 'text-zinc-500 dark:text-zinc-400';
                                $deadlineIcon = 'calendar';
                                
                                if ($po->expected_delivery_date) {
                                    $expected = \Carbon\Carbon::parse($po->expected_delivery_date)->startOfDay();
                                    $today = \Carbon\Carbon::now()->startOfDay();
                                    $diff = $today->diffInDays($expected, false);
                                    
                                    if ($po->status === 'completed' || $po->status === 'archived') {
                                        $deadlineStr = 'Tuntas';
                                        $deadlineColor = 'text-emerald-600 dark:text-emerald-400 font-semibold';
                                        $deadlineIcon = 'check-circle';
                                    } else {
                                        if ($diff < 0) {
                                            $deadlineStr = 'Telat ' . abs(intval($diff)) . ' Hari';
                                            $deadlineColor = 'text-red-600 dark:text-red-400 font-bold';
                                            $deadlineIcon = 'exclamation-circle';
                                        } elseif ($diff == 0) {
                                            $deadlineStr = 'Hari Ini';
                                            $deadlineColor = 'text-amber-600 dark:text-amber-500 font-bold';
                                            $deadlineIcon = 'clock';
                                        } elseif ($diff <= 3) {
                                            $deadlineStr = 'Sisa ' . intval($diff) . ' Hari';
                                            $deadlineColor = 'text-amber-600 dark:text-amber-500 font-semibold';
                                            $deadlineIcon = 'clock';
                                        } else {
                                            $deadlineStr = 'Sisa ' . intval($diff) . ' Hari';
                                        }
                                    }
                                } else {
                                    $deadlineStr = 'Tdk ada deadline';
                                }
                            @endphp
                            <div wire:key="po-{{ $po->id }}" 
                                 @click="activeId = '{{ $po->id }}'; $dispatch('open-detail-modal', { orderId: {{ $po->id }} })"
                                 :class="{ 'opacity-50 pointer-events-none animate-pulse grayscale': processingId == '{{ $po->id }}' }"
                                 class="bg-white dark:bg-zinc-800 p-2 rounded-lg shadow-sm border transition-all duration-200 active:scale-[0.98] active:shadow-none {{ $status === 'void' ? 'border-red-400 dark:border-red-500 bg-red-50/30 dark:bg-red-900/10' : ($isCustom ? 'border-amber-400 dark:border-amber-500 shadow-amber-500/20 hover:-translate-y-0.5 hover:border-amber-500 hover:shadow-amber-500/30' : 'border-zinc-200 dark:border-zinc-700 hover:-translate-y-0.5 hover:shadow-sm hover:border-'.$column['color'].'-400 dark:hover:border-'.$column['color'].'-500') }} group relative cursor-pointer flex flex-col gap-1.5">
                                {{-- Header Card --}}
                                <div class="flex justify-between items-center relative z-10">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono text-[9px] font-bold text-zinc-600 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-700/50 border border-zinc-200 dark:border-zinc-600 px-1 py-px rounded w-max">
                                            {{ $po->po_number }}
                                        </span>
                                        @if($isCustom)
                                            <span class="text-[8px] font-black text-amber-600 bg-amber-100 border border-amber-200 px-1 py-px rounded shadow-sm flex items-center gap-0.5 w-max">
                                                <flux:icon.sparkles class="w-2 h-2" /> CUSTOM
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 text-[8px]">
                                        <div class="flex items-center text-zinc-500 dark:text-zinc-400 font-medium">
                                            <flux:icon.calendar class="w-2 h-2 mr-0.5" />
                                            {{ \Carbon\Carbon::parse($po->order_date)->format('d M') }}
                                        </div>
                                        <div class="flex items-center {{ $deadlineColor }}">
                                            @if($deadlineIcon === 'calendar')
                                                <flux:icon.calendar class="w-2 h-2 mr-0.5" />
                                            @elseif($deadlineIcon === 'check-circle')
                                                <flux:icon.check-circle class="w-2 h-2 mr-0.5" />
                                            @elseif($deadlineIcon === 'exclamation-circle')
                                                <flux:icon.exclamation-circle class="w-2 h-2 mr-0.5" />
                                            @elseif($deadlineIcon === 'clock')
                                                <flux:icon.clock class="w-2 h-2 mr-0.5" />
                                            @endif
                                            {{ $deadlineStr }}
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2 items-center">
                                    {{-- Vendor Info --}}
                                    <div class="flex items-center gap-1.5 overflow-hidden">
                                        <div class="relative shrink-0">
                                            <flux:avatar src="{{ $po->vendor?->image ? Storage::url($po->vendor->image) : '' }}" fallback="{{ substr($po->vendor?->name ?? '?', 0, 2) }}" class="!w-6 !h-6 shadow-sm" />
                                        </div>
                                        <div class="flex flex-col overflow-hidden leading-tight gap-0.5">
                                            <span class="font-bold text-[10px] text-zinc-800 dark:text-zinc-100 truncate">
                                                {{ $po->vendor?->name ?? 'Vendor Terhapus' }}
                                            </span>
                                            <div class="flex items-center gap-1 flex-wrap">
                                                <span class="text-[7px] font-medium text-blue-600 dark:text-blue-300 bg-blue-100 dark:bg-blue-500/20 px-1 py-px rounded w-max">
                                                    {{ $po->vendor?->type ?: 'Supplier' }}
                                                </span>
                                                <span class="text-[9px] font-black text-zinc-900 dark:text-white tracking-tight">
                                                    Rp {{ number_format($po->total_amount, 0, ',', '.') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Progress Bars --}}
                                    <div class="flex flex-col gap-1.5 border-l border-zinc-100 dark:border-zinc-800 pl-2">
                                        {{-- Receiving --}}
                                        <div>
                                            <div class="flex justify-between items-end mb-0.5">
                                                <span class="text-[7px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider flex items-center gap-0.5">
                                                    <flux:icon.cube class="w-1.5 h-1.5" /> Trims
                                                    <span class="lowercase text-[6px] ml-0.5 opacity-70">({{ $skuCount }} sku)</span>
                                                </span>
                                                <span class="text-[7px] font-semibold {{ $receivePercent >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-200' }}">
                                                    {{ $receivedQty }}/{{ $totalQty }}
                                                </span>
                                            </div>
                                            <div class="w-full h-1 bg-zinc-100 dark:bg-zinc-700/50 rounded-full overflow-hidden">
                                                <div class="h-full {{ $receivePercent >= 100 ? 'bg-emerald-500' : 'bg-blue-500' }} rounded-full" style="width: {{ $receivePercent }}%"></div>
                                            </div>
                                        </div>
                                        
                                        {{-- Payment --}}
                                        <div>
                                            <div class="flex justify-between items-end mb-0.5">
                                                <span class="text-[7px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider flex items-center gap-0.5">
                                                    <flux:icon.banknotes class="w-1.5 h-1.5" /> Byr
                                                </span>
                                                <span class="text-[7px] font-semibold {{ $paymentPercent >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-200' }}">
                                                    {{ $paymentPercent }}%
                                                </span>
                                            </div>
                                            <div class="w-full h-1 bg-zinc-100 dark:bg-zinc-700/50 rounded-full overflow-hidden">
                                                <div class="h-full {{ $paymentPercent >= 100 ? 'bg-emerald-500' : 'bg-indigo-500' }} rounded-full" style="width: {{ $paymentPercent }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>
                    @empty
                        <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400 dark:text-zinc-500">
                            Kosong
                        </div>
                    @endforelse
                    
                    @if(count($this->orders[$statusKey] ?? []) >= ($columnLimits[$statusKey] ?? 24))
                        <x-kanban.load-more :statusKey="$statusKey" />
                    @endif
            </x-kanban.column>
        @endforeach
    </x-kanban.board>
    </div>

    {{-- Table View --}}
    <div wire:key="view-table-wrapper" class="w-full {{ $this->viewMode === 'table' ? 'block' : 'hidden' }}">
        <x-table.header searchModel="search" searchPlaceholder="Cari PO / Vendor...">
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

                @can('purchase.create')
                    <flux:button variant="primary" icon="plus" href="{{ route('purchase.orders.create') }}" wire:navigate class="shrink-0 px-2 sm:px-4">
                        <span class="hidden sm:inline">Buat PO</span>
                        <span class="sm:hidden text-[9px]">Buat</span>
                    </flux:button>
                @endcan
        </x-table.header>

        <x-table.wrapper>
                    <flux:table class="table-mobile-cards">
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
                                        <div class="pl-2 sm:pl-4 flex items-center gap-2">
                                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100 whitespace-nowrap">{{ $order->po_number }}</div>
                                            <div class="text-xs text-zinc-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($order->order_date)->format('d M Y') }}</div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    <flux:table.cell>
                                        <div class="flex items-start gap-3 mt-1">
                                            <flux:avatar src="{{ $order->vendor?->image ? Storage::url($order->vendor->image) : '' }}" fallback="{{ substr($order->vendor?->name ?? '?', 0, 2) }}" size="sm" />
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $order->vendor?->name ?? 'Vendor Terhapus' }}</div>
                                                    <div class="text-[11px] text-zinc-500 flex items-center gap-1.5">
                                                        @if($order->creator->avatar ?? false)
                                                            <img src="{{ Storage::url($order->creator->avatar) }}" class="w-3.5 h-3.5 rounded-full object-cover" />
                                                        @else
                                                            <div class="w-3.5 h-3.5 rounded-full bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 flex items-center justify-center text-[8px] font-bold">
                                                                {{ strtoupper(substr($order->creator->name ?? 'A', 0, 1)) }}
                                                            </div>
                                                        @endif
                                                        <span>{{ explode(' ', $order->creator->name ?? '-')[0] }}</span>
                                                    </div>
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
                                    
                                    <flux:table.cell class="card-status-overlay">
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
        </x-table.wrapper>
        
        <div class="px-0 sm:px-4 lg:px-6 pb-24">
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
    
    @if(session('new_po_number'))
        <div x-data x-init="setTimeout(() => { $flux.modal('new-po-success-modal')?.show() }, 500)">
            <flux:modal name="new-po-success-modal" class="min-w-[22rem]">
                <div class="p-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 dark:bg-emerald-900/30 mb-4">
                        <flux:icon.check-circle class="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <flux:heading size="xl">Berhasil!</flux:heading>
                    <flux:subheading class="mt-2 text-sm">
                        Purchase Order baru berhasil dibuat:
                    </flux:subheading>
                    <div class="mt-3 mb-6 bg-zinc-50 dark:bg-zinc-800/50 py-3 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-wider">{{ session('new_po_number') }}</span>
                    </div>
                    
                    <div class="mt-4 flex flex-col gap-2" x-data="{
                        printing: false,
                        printPO(url, filename) {
                            if (this.printing) return;
                            
                            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                            
                            if (isMobile) {
                                window.open(url, '_blank');
                                return;
                            }
                            
                            this.printing = true;
                            
                            const originalTitle = document.title;
                            document.title = filename;

                            const iframe = document.createElement('iframe');
                            iframe.style.display = 'none';
                            iframe.src = url;
                            document.body.appendChild(iframe);
                            iframe.onload = () => {
                                iframe.contentWindow.focus();
                                iframe.contentWindow.print();
                                this.printing = false;
                                
                                setTimeout(() => {
                                    document.title = originalTitle;
                                    document.body.removeChild(iframe);
                                }, 5000);
                            };
                        }
                    }">
                        @if(session('new_po_id'))
                            <flux:button variant="primary" class="w-full" icon="printer" x-on:click="printPO('{{ route('purchase.orders.print', session('new_po_id')) }}', '{{ session('new_po_number') }}')" x-bind:disabled="printing">
                                <span x-show="!printing">Cetak PO</span>
                                <span x-show="printing" style="display: none;">Mencetak...</span>
                            </flux:button>
                        @endif
                        <flux:button variant="ghost" class="w-full" @click="$flux.modal('new-po-success-modal').close()">Tutup</flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    @endif

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
