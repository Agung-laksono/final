<?php
use function Livewire\Volt\{state, layout, title, computed, on, usesPagination, mount};
use Modules\Sales\Models\SalesOrder;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

usesPagination(theme: 'tailwind');

layout('layouts.app');
title('Pengiriman Penjualan (Gudang)');

state([
    'viewMode' => 'kanban',
    'groupBy' => 'so',
    'transparent_columns' => false,
    'search' => '',
    'setting_version' => 0, // bumped saat setting berubah → paksa re-render
]);

mount(function () {
    $this->viewMode = session()->get('sales_delivery_view_mode', 'kanban');
    $this->groupBy = session()->get('sales_delivery_group_by', 'so');
});

$updatedViewMode = function ($value) {
    session()->put('sales_delivery_view_mode', $value);
};

$setGroupBy = function ($value) {
    $this->groupBy = $value;
    session()->put('sales_delivery_group_by', $value);
};

$markAsShipped = function ($orderId) {
    $order = SalesOrder::find($orderId);
    if (!$order || $order->status !== 'packing') return;
    
    $order->status = 'shipping';
    $order->save();
    
    // Update status barcode dari booked menjadi sold
    $labelIds = \Modules\Sales\Models\SalesOrderFulfillment::where('sales_order_id', $order->id)
        ->whereNotNull('item_label_id')
        ->pluck('item_label_id');
        
    if ($labelIds->isNotEmpty()) {
        $labels = \Modules\Inventory\Models\ItemLabel::whereIn('id', $labelIds)->where('status', 'booked')->get();
        foreach($labels as $lbl) {
            $lbl->status = 'sold';
            $lbl->notes = $lbl->notes . "\n[Shipping]: Telah diserahkan ke Ekspedisi.";
            $lbl->save();
        }
    }
    
    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('sales_order');
    \Flux::toast('Pesanan berhasil diserahkan ke kurir!', variant: 'success');
};

$activeColumns = computed(function () {
    // Membaca setting_version agar computed ini di-evaluasi ulang saat setting berubah
    $_ = $this->setting_version;
    $showShipping = Cache::remember('setting_gudang_handles_shipping', 3600, function () {
        return Setting::where('key', 'gudang_handles_shipping')->value('value');
    }) == '1';
    
    $cols = [
        'processing' => ['title' => 'Tarik Barang', 'color' => 'blue'],
        'packing' => ['title' => 'Proses Packing', 'color' => 'purple'],
    ];
    if ($showShipping) {
        $cols['ready_to_ship'] = ['title' => 'Tunggu Kurir', 'color' => 'orange'];
    }
    return $cols;
});

$tableDeliveries = computed(function () {
    if ($this->viewMode !== 'table') {
        return null;
    }
    
    $_ = $this->setting_version;
    $showShipping = Cache::remember('setting_gudang_handles_shipping', 3600, function () {
        return Setting::where('key', 'gudang_handles_shipping')->value('value');
    }) == '1';
    $statuses = ['processing', 'packing'];
    
    $query = SalesOrder::with(['customer', 'creator', 'items.item', 'fulfillments'])
        ->whereIn('status', $statuses);
        
    if (!$showShipping) {
        $query->where(function($q) {
            $q->where('status', '!=', 'packing')->orWhere('is_packed', false);
        });
    }
    $query->latest();
    
    if ($this->search) {
        $query->where(function($q) {
            $q->where('so_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('customer', function($q2) {
                  $q2->where('name', 'like', '%' . $this->search . '%');
              });
        });
    }
    
    return $query->paginate(15);
});

$orders = computed(function () {
    if ($this->viewMode !== 'kanban') {
        return [];
    }
    
    // Membaca setting_version agar computed ini di-evaluasi ulang saat setting berubah
    $_ = $this->setting_version;
    $showShipping = Cache::remember('setting_gudang_handles_shipping', 3600, function () {
        return Setting::where('key', 'gudang_handles_shipping')->value('value');
    }) == '1';
    $statuses = ['processing', 'packing'];
    
    $query = SalesOrder::with(['customer', 'creator', 'items.item.unit', 'fulfillments'])
        ->whereIn('status', $statuses);
        
    if (!$showShipping) {
        $query->where(function($q) {
            $q->where('status', '!=', 'packing')->orWhere('is_packed', false);
        });
    }
    $query->latest();
    
    if ($this->search) {
        $query->where(function($q) {
            $q->where('so_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('customer', function($q2) {
                  $q2->where('name', 'like', '%' . $this->search . '%');
              });
        });
    }
    
    $result = $query->get();
    $groupedByColumn = [];
    
    foreach (array_keys($this->activeColumns) as $colKey) {
        if ($colKey === 'ready_to_ship') {
            $columnOrders = $result->where('status', 'packing')->where('is_packed', true);
        } elseif ($colKey === 'packing') {
            $columnOrders = $result->where('status', 'packing')->where('is_packed', false);
        } else {
            $columnOrders = $result->where('status', $colKey);
        }
        
        if ($this->groupBy === 'item' && $colKey === 'processing') {
            $itemMap = [];
            foreach ($columnOrders as $order) {
                foreach ($order->items as $orderItem) {
                    $itemId = $orderItem->item_id;
                    $needed = $orderItem->qty;
                    $alreadyConsumed = $order->fulfillments->where('item_id', $itemId)->sum('scanned_qty');
                    
                    if (!isset($itemMap[$itemId])) {
                        $itemMap[$itemId] = [
                            'is_item' => true,
                            'id' => 'item_' . $itemId,
                            'item_id' => $itemId,
                            'item' => $orderItem->item,
                            'requested_qty' => 0,
                            'fulfilled_qty' => 0,
                            'sos' => [],
                            'status' => $colKey,
                        ];
                    }
                    
                    $itemMap[$itemId]['requested_qty'] += $needed;
                    $itemMap[$itemId]['fulfilled_qty'] += $alreadyConsumed;
                    $itemMap[$itemId]['sos'][] = $order;
                }
            }
            $groupedByColumn[$colKey] = collect(array_values($itemMap));
        } else {
            $groupedByColumn[$colKey] = $columnOrders;
        }
    }
    
    return collect($groupedByColumn);
});

on([
    'status-updated' => function () {},
    'echo:kanban.sales_order,KanbanUpdated' => function () {},
    'echo:settings,SettingUpdated' => function () {
        Cache::forget('setting_gudang_handles_shipping');
        $this->setting_version++;
    },
]);
?>

<div>
<x-kanban.board componentId="sales_delivery" :viewMode="$viewMode" title="Pengiriman Penjualan" subtitle="Pantau pemenuhan, pengemasan, dan pengiriman (Kanban).">
    <x-slot:actions>
        <div class="flex items-center gap-1.5 sm:gap-2">
            <div class="flex border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden shrink-0">
                <button type="button" wire:click="setGroupBy('so')" wire:loading.attr="disabled" class="p-1 sm:p-1.5 px-2 sm:px-2.5 text-xs sm:text-[11px] font-bold transition-colors flex items-center gap-1 {{ $groupBy === 'so' ? 'bg-indigo-600 dark:bg-indigo-500 text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 bg-white dark:bg-zinc-900' }}" title="Kelompokkan berdasarkan SO">
                    <flux:icon.arrow-path wire:loading wire:target="setGroupBy('so')" class="w-3 h-3 animate-spin shrink-0" />
                    <span wire:loading.remove wire:target="setGroupBy('so')" class="sm:hidden">SO</span>
                    <span wire:loading.remove wire:target="setGroupBy('so')" class="hidden sm:inline">SPK (SO)</span>
                    <span wire:loading wire:target="setGroupBy('so')" class="hidden sm:inline text-[9px] font-normal opacity-85"></span>
                </button>
                <button type="button" wire:click="setGroupBy('item')" wire:loading.attr="disabled" class="p-1 sm:p-1.5 px-2 sm:px-2.5 text-xs sm:text-[11px] font-bold transition-colors flex items-center gap-1 {{ $groupBy === 'item' ? 'bg-indigo-600 dark:bg-indigo-500 text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 bg-white dark:bg-zinc-900' }}" title="Kelompokkan berdasarkan Produk">
                    <flux:icon.arrow-path wire:loading wire:target="setGroupBy('item')" class="w-3 h-3 animate-spin shrink-0" />
                    <span wire:loading.remove wire:target="setGroupBy('item')" class="sm:hidden">Produk</span>
                    <span wire:loading.remove wire:target="setGroupBy('item')" class="hidden sm:inline">Item Produk</span>
                    <span wire:loading wire:target="setGroupBy('item')" class="hidden sm:inline text-[9px] font-normal opacity-85"></span>
                </button>
            </div>
        </div>
    </x-slot:actions>
    <x-slot:kanban_layout>
        <div class="flex gap-6 overflow-x-auto pb-4 h-full -mx-4 px-4 md:mx-0 md:px-0 snap-x snap-mandatory scroll-smooth custom-scrollbar items-stretch min-w-full w-max before:content-[''] before:m-auto after:content-[''] after:m-auto">
            @foreach($this->activeColumns as $statusKey => $column)
                @php
                    $defaultCollapsed = false;
                @endphp
                <x-kanban.column 
                    :statusKey="$statusKey" 
                    :column="$column" 
                    :componentId="'inventory'" 
                    :count="count($this->orders[$statusKey] ?? [])"
                    :defaultCollapsed="$defaultCollapsed"
                >
                        
                        @forelse($this->orders[$statusKey] ?? [] as $order)
                            @if(is_array($order) && isset($order['is_item']))
                                @php 
                                    $itemData = $order; 
                                    $isItemCustom = collect($itemData['sos'])->contains(function($so) use ($itemData) {
                                        $soItem = $so->items->firstWhere('item_id', $itemData['item_id']);
                                        return $soItem && (!empty($soItem->custom_attributes) || !empty($soItem->custom_attachments));
                                    });
                                @endphp
                                <div wire:key="kanban-item-{{ $itemData['item_id'] }}-{{ $statusKey }}"
                                     x-data="{ expanded: false, isDesktop: window.matchMedia('(min-width: 1099px)').matches, loading: false, lastClick: 0 }"
                                     @resize.window="isDesktop = window.matchMedia('(min-width: 1099px)').matches"
                                     @mouseenter="if(isDesktop) expanded = true"
                                     @mouseleave="if(isDesktop) expanded = false"
                                     @click.outside="if(!isDesktop) expanded = false"
                                     @open-item-fulfillment-modal.window="if($event.detail.itemId === {{ $itemData['item_id'] }}) { loading = true; setTimeout(() => loading = false, 800); }"
                                     @modal-closed.window="loading = false"
                                     @status-updated.window="loading = false"
                                     class="bg-white dark:bg-zinc-800 rounded-xl p-2 shadow-sm border transition-all duration-300 active:scale-[0.98] cursor-pointer group flex flex-col gap-1.5 relative overflow-hidden {{ $isItemCustom ? 'border-amber-400 dark:border-amber-500 shadow-amber-500/20 hover:-translate-y-1 hover:border-amber-500 hover:shadow-amber-500/30' : 'border-zinc-200 dark:border-zinc-700 hover:shadow-md hover:-translate-y-1 hover:border-'.$column['color'].'-400 dark:hover:border-'.$column['color'].'-500' }}" 
                                     :class="{ 'animate-pulse opacity-60 pointer-events-none': loading }"
                                     x-on:click="
                                        if(isDesktop) {
                                            $dispatch('open-item-fulfillment-modal', { itemId: {{ $itemData['item_id'] }} });
                                        } else {
                                            let now = Date.now();
                                            if (now - lastClick < 400) {
                                                $dispatch('open-item-fulfillment-modal', { itemId: {{ $itemData['item_id'] }} });
                                            } else {
                                                expanded = !expanded;
                                            }
                                            lastClick = now;
                                        }
                                     ">
                                     
                                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-{{ $column['color'] }}-400/50"></div>
                                    
                                    <div class="flex items-center gap-2 pl-1.5">
                                        @if($itemData['item']->image)
                                            <flux:avatar src="{{ Storage::url($itemData['item']->image) }}" loading="lazy" fallback="{{ substr($itemData['item']->name ?? '?', 0, 2) }}" class="w-10 h-10 rounded-md shrink-0 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 transition-transform duration-300 group-active:scale-95 cursor-zoom-in hover:scale-105 hover:shadow-md" @click.stop="$dispatch('open-lightbox', { url: '{{ Storage::url($itemData['item']->image) }}' })" />
                                        @else
                                            <flux:avatar fallback="{{ substr($itemData['item']->name ?? '?', 0, 2) }}" class="w-10 h-10 rounded-md shrink-0 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 transition-transform duration-300 group-active:scale-95" />
                                        @endif
                                        
                                        <div class="flex flex-col flex-1 min-w-0">
                                            <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs leading-tight truncate pr-2" title="{{ $itemData['item']->name }}">{{ $itemData['item']->name }}</h4>
                                            <div class="flex items-baseline gap-1 mt-0.5">
                                                <div class="text-[10px] font-black">
                                                    <span class="text-red-500 dark:text-red-400">{{ $itemData['fulfilled_qty'] }}</span>
                                                    <span class="text-zinc-400 font-medium">/</span>
                                                    <span class="text-emerald-600 dark:text-emerald-500">{{ $itemData['requested_qty'] }}</span>
                                                </div>
                                                <span class="uppercase text-[8px] font-bold tracking-wider text-zinc-400 mr-2">{{ $itemData['item']->unit->name ?? 'pcs' }}</span>
                                                <span class="text-[9px] text-zinc-500 dark:text-zinc-400 font-medium border-l border-zinc-200 dark:border-zinc-700 pl-2 shrink-0">
                                                    {{ count($itemData['sos']) }} SO
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div x-show="expanded" x-collapse.duration.300ms>
                                        <div class="pl-1.5 pt-2 mt-1 border-t border-zinc-100 dark:border-zinc-700/50 flex flex-col gap-1.5 text-[10px]">
                                            @foreach($itemData['sos'] as $so)
                                                @php
                                                    $soItem = $so->items->firstWhere('item_id', $itemData['item_id']);
                                                    $qty = $soItem ? $soItem->qty : 0;
                                                    $isSoItemCustom = $soItem && (!empty($soItem->custom_attributes) || !empty($soItem->custom_attachments));
                                                @endphp
                                                <button type="button" @click.stop="$dispatch('open-fulfillment-modal', { orderId: {{ $so->id }} })" class="flex flex-col text-left p-1.5 rounded bg-zinc-50 dark:bg-zinc-900/40 hover:bg-zinc-100 dark:hover:bg-zinc-950 transition-all duration-300 border border-zinc-200/50 dark:border-zinc-800/50 active:scale-[0.98] w-full cursor-pointer" title="Buka Detail SPK">
                                                    <div class="flex items-center justify-between font-bold text-indigo-600 dark:text-indigo-400">
                                                        <div class="flex items-center gap-1.5">
                                                            <span>{{ $so->so_number }}</span>
                                                            @if($isSoItemCustom)
                                                                <span class="text-[7px] font-black text-amber-600 bg-amber-100 border border-amber-200 px-1 py-px rounded shadow-sm flex items-center gap-0.5 w-max">
                                                                    <flux:icon.sparkles class="w-2 h-2" /> CUSTOM
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <span class="text-zinc-700 dark:text-zinc-300 font-mono text-[9px] shrink-0">{{ $qty }} {{ $itemData['item']->unit->name ?? 'pcs' }}</span>
                                                    </div>
                                                    <div class="text-[9px] text-zinc-500 dark:text-zinc-400 truncate mt-0.5" title="{{ $so->customer->name ?? 'Pelanggan' }}">
                                                        {{ $so->customer->name ?? 'Pelanggan' }} <span class="text-zinc-400 font-mono text-[8px]">({{ $so->created_at->format('d/m/Y') }})</span>
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @else
                                @php
                                    $isCustom = $order->items->contains(function($item) {
                                        return !empty($item->custom_attributes) || !empty($item->custom_attachments);
                                    }) || str_contains(strtoupper($order->notes ?? ''), '[CUSTOM]');
                                @endphp
                                <div wire:key="order-{{ $order->id }}"
                                     x-data="{ expanded: false, isDesktop: window.matchMedia('(min-width: 1099px)').matches, loading: false, lastClick: 0 }"
                                     @resize.window="isDesktop = window.matchMedia('(min-width: 1099px)').matches"
                                     @mouseenter="if(isDesktop) expanded = true"
                                     @mouseleave="if(isDesktop) expanded = false"
                                     @click.outside="if(!isDesktop) expanded = false"
                                     @open-fulfillment-modal.window="if($event.detail.orderId === {{ $order->id }}) { loading = true; setTimeout(() => loading = false, 800); }"
                                     @open-packing-modal.window="if($event.detail.orderId === {{ $order->id }}) { loading = true; setTimeout(() => loading = false, 800); }"
                                     @open-shipping-modal.window="if($event.detail.orderId === {{ $order->id }}) { loading = true; setTimeout(() => loading = false, 800); }"
                                     @modal-closed.window="loading = false"
                                     @status-updated.window="loading = false"
                                     class="bg-white dark:bg-zinc-800 rounded-xl p-2 shadow-sm border transition-all duration-300 active:scale-[0.98] cursor-pointer group flex flex-col gap-0.5 relative overflow-hidden {{ $isCustom ? 'border-amber-400 dark:border-amber-500 shadow-amber-500/20 hover:-translate-y-1 hover:border-amber-500 hover:shadow-amber-500/30' : 'border-zinc-200 dark:border-zinc-700 hover:shadow-md hover:-translate-y-1 hover:border-'.$column['color'].'-400 dark:hover:border-'.$column['color'].'-500' }}" 
                                     :class="{ 'animate-pulse opacity-60 pointer-events-none': loading }"
                                 x-on:click="
                                    if(isDesktop) {
                                        let status = '{{ $statusKey }}';
                                        if (status === 'processing') $dispatch('open-fulfillment-modal', { orderId: {{ $order->id }} });
                                        else if (status === 'packing') $dispatch('open-packing-modal', { orderId: {{ $order->id }} });
                                        else if (status === 'shipping') $dispatch('open-shipping-modal', { orderId: {{ $order->id }} });
                                    } else {
                                        let now = Date.now();
                                        if (now - lastClick < 400) {
                                            if (status === 'processing') $dispatch('open-fulfillment-modal', { orderId: {{ $order->id }} });
                                            else if (status === 'packing') $dispatch('open-packing-modal', { orderId: {{ $order->id }} });
                                            else if (status === 'shipping') $dispatch('open-shipping-modal', { orderId: {{ $order->id }} });
                                        } else {
                                            expanded = !expanded;
                                        }
                                        lastClick = now;
                                    }
                                 ">
                                
                                @php
                                    $orderedQty = $order->items->sum('qty');
                                    $fulfilledQty = $order->fulfillments->sum('scanned_qty');
                                    $progressPercent = $orderedQty > 0 ? min(100, round(($fulfilledQty / $orderedQty) * 100)) : 0;
                                @endphp
                                
                                {{-- Status Marker Line --}}
                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-{{ $column['color'] }}-400/50"></div>
                                
                                {{-- Header Card --}}
                                <div class="flex justify-between items-center relative z-10">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono text-[9px] font-bold text-zinc-600 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-700/50 border border-zinc-200 dark:border-zinc-600 px-1 py-px rounded w-max">
                                            {{ $order->so_number }}
                                        </span>
                                        @if($isCustom)
                                            <span class="text-[8px] font-black text-amber-600 bg-amber-100 border border-amber-200 px-1 py-px rounded shadow-sm flex items-center gap-0.5 w-max">
                                                <flux:icon.sparkles class="w-2 h-2" /> CUSTOM
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <div class="flex items-center text-zinc-500 dark:text-zinc-400 font-medium text-[8px]" title="{{ $order->created_at->format('d M Y H:i') }}">
                                            <flux:icon.calendar class="w-2 h-2 mr-0.5" />
                                            {{ $order->created_at->format('d M') }}
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Customer & Progress Grid --}}
                                <div class="grid grid-cols-2 gap-2 items-center mt-1">
                                    <div class="flex items-center gap-1.5 overflow-hidden">
                                        <div class="relative shrink-0">
                                            <flux:avatar src="{{ $order->customer->image ? Storage::url($order->customer->image) : '' }}" fallback="{{ substr($order->customer->name ?? 'T', 0, 2) }}" class="!w-6 !h-6 shadow-sm" />
                                        </div>
                                        <div class="flex flex-col overflow-hidden leading-tight gap-0.5">
                                            <span class="font-bold text-[10px] text-zinc-800 dark:text-zinc-100 truncate" title="{{ $order->customer->name ?? 'Pelanggan Tunai' }}">
                                                {{ $order->customer->name ?? 'Pelanggan Tunai' }}
                                            </span>
                                            <div class="flex items-center gap-1 overflow-hidden">
                                                @php
                                                    $cityName = $order->customer->city ?? 'Tanpa Kota';
                                                    $shortCity = str_ireplace(['Kabupaten ', 'Kecamatan '], ['Kab. ', 'Kec. '], $cityName);
                                                @endphp
                                                <span class="text-[6px] font-semibold text-blue-600 dark:text-blue-300 bg-blue-100 dark:bg-blue-500/20 px-1 py-px rounded truncate min-w-0" title="{{ $cityName }}">
                                                    {{ $shortCity }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {{-- Progress Bars --}}
                                    <div class="flex flex-col gap-1.5 border-l border-zinc-100 dark:border-zinc-800 pl-2">
                                        {{-- Fulfillment --}}
                                        <div>
                                            <div class="flex justify-between items-end mb-0.5">
                                                <span class="text-[7px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider flex items-center gap-0.5">
                                                    <flux:icon.cube class="w-1.5 h-1.5" /> PRODUK
                                                    <span class="text-[6px] normal-case tracking-normal font-medium text-zinc-400 dark:text-zinc-500 ml-0.5">({{ $order->items->count() }} SKU)</span>
                                                </span>
                                                <span class="text-[7px] font-semibold {{ $progressPercent >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-200' }}">
                                                    {{ $fulfilledQty }}/{{ $orderedQty }}
                                                </span>
                                            </div>
                                            <div class="w-full h-1 bg-zinc-100 dark:bg-zinc-700/50 rounded-full overflow-hidden">
                                                <div class="h-full {{ $progressPercent >= 100 ? 'bg-emerald-500' : 'bg-blue-500' }} rounded-full" style="width: {{ $progressPercent }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div x-show="expanded" x-collapse.duration.300ms>
                                    <div class="pl-1.5 pt-2 mt-2 border-t border-zinc-100 dark:border-zinc-700/50 flex flex-col gap-1.5">
                                        {{-- SKU List Dropdown --}}
                                        <div class="flex flex-col gap-1">
                                            @foreach($order->items as $item)
                                                @php
                                                    $isSoItemCustom = !empty($item->custom_attributes) || !empty($item->custom_attachments);
                                                    $itemFulfilled = $order->fulfillments->where('item_id', $item->item_id)->sum('scanned_qty');
                                                    $isComplete = $itemFulfilled >= $item->qty;
                                                @endphp
                                                <div class="flex items-center justify-between p-1.5 rounded bg-zinc-50 dark:bg-zinc-900/40 border border-zinc-200/50 dark:border-zinc-800/50 text-[10px]">
                                                    <div class="flex flex-col min-w-0 pr-2">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="font-bold text-zinc-700 dark:text-zinc-300 truncate" title="{{ $item->item->name }}">{{ $item->item->name }}</span>
                                                            @if($isSoItemCustom)
                                                                <span class="text-[7px] font-black text-amber-600 bg-amber-100 border border-amber-200 px-1 py-px rounded shadow-sm shrink-0 flex items-center gap-0.5">
                                                                    <flux:icon.sparkles class="w-2 h-2" /> CUSTOM
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <span class="text-[8px] text-zinc-400 mt-0.5 truncate">{{ $item->item->code ?? 'No SKU' }}</span>
                                                    </div>
                                                    <div class="flex flex-col items-end shrink-0">
                                                        <span class="font-black {{ $isComplete ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                                                            {{ $itemFulfilled }}/{{ $item->qty }}
                                                        </span>
                                                        <span class="text-[8px] text-zinc-400 font-mono">{{ $item->item->unit->name ?? 'pcs' }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        
                                        @if(in_array($order->status, ['packing', 'shipping']))
                                            @if($order->packing_receipt_path)
                                                <div class="flex items-center justify-between text-[10px] bg-zinc-50 dark:bg-zinc-900/40 p-1.5 rounded border border-zinc-200/50 dark:border-zinc-800/50 mt-0.5">
                                                    <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                                        <flux:icon.archive-box class="w-3 h-3 text-purple-500 shrink-0" /> 
                                                        <span class="truncate font-medium">Ada Foto Packing</span>
                                                    </div>
                                                    <a href="{{ Storage::url($order->packing_receipt_path) }}" target="_blank" class="text-purple-600 hover:text-purple-700 shrink-0 bg-purple-50 dark:bg-purple-900/30 p-1 rounded" title="Lihat Foto" @click.stop><flux:icon.document-check class="w-3 h-3" /></a>
                                                </div>
                                            @endif
                                            
                                            @php
                                                $gudangHandlesShipping = \Illuminate\Support\Facades\Cache::remember('setting_gudang_handles_shipping', 3600, function () {
                                                    return \App\Models\Setting::where('key', 'gudang_handles_shipping')->value('value');
                                                }) == '1';
                                            @endphp
                                            @if($statusKey === 'ready_to_ship' && $gudangHandlesShipping)
                                                @if($order->courier_vendor_id)
                                                    <div class="flex gap-1.5 w-full mt-1">
                                                        <flux:button variant="primary" size="sm" class="flex-1 text-[10px] h-6 py-0 min-h-0 bg-blue-600 hover:bg-blue-700 text-white border-none" wire:click.stop="markAsShipped({{ $order->id }})">
                                                            Telah Diambil
                                                        </flux:button>
                                                        <flux:button variant="subtle" size="sm" class="w-8 shrink-0 text-[10px] h-6 py-0 px-0 min-h-0 bg-zinc-50 dark:bg-zinc-900/40 border border-zinc-200/50 dark:border-zinc-800/50 flex items-center justify-center" title="Edit Resi" @click.stop="Livewire.dispatch('open-shipping-modal', { orderId: {{ $order->id }} })">
                                                            <flux:icon.pencil-square class="w-3 h-3" />
                                                        </flux:button>
                                                    </div>
                                                @else
                                                    <flux:button variant="subtle" size="sm" class="w-full text-[10px] h-6 py-0 min-h-0 bg-zinc-50 dark:bg-zinc-900/40 border border-zinc-200/50 dark:border-zinc-800/50" @click.stop="Livewire.dispatch('open-shipping-modal', { orderId: {{ $order->id }} })">
                                                        Input Ekspedisi
                                                    </flux:button>
                                                @endif
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endif
                        @empty
                            <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400 dark:text-zinc-500">
                                Kosong
                            </div>
                        @endforelse
                </x-kanban.column>
            @endforeach
        </div>
    </x-slot:kanban_layout>

    <x-slot:table_layout>
        <x-table.wrapper class="mb-6">
            <flux:table class="table-mobile-cards">
                <flux:table.columns>
                    <flux:table.column>No. SO & Pelanggan</flux:table.column>
                    <flux:table.column>Progres Tarik Barang</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Tindakan</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @if($this->tableDeliveries)
                        @forelse($this->tableDeliveries as $order)
                            <flux:table.row wire:key="row-{{ $order->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <flux:table.cell>
                                    <div class="flex items-start gap-3 mt-1 sm:mt-0">
                                        <flux:avatar src="{{ $order->customer->image ? Storage::url($order->customer->image) : '' }}" fallback="{{ substr($order->customer->name ?? 'T', 0, 2) }}" size="sm" class="shrink-0" />
                                        <div>
                                            <div class="font-medium text-sm text-zinc-900 dark:text-white line-clamp-1">{{ $order->customer->name ?? 'Pelanggan Tunai' }}</div>
                                            <div class="text-[11px] text-zinc-500 flex items-center gap-1.5 mt-0.5">
                                                <span class="font-mono bg-zinc-100 dark:bg-zinc-800 px-1 rounded text-zinc-600 dark:text-zinc-400">{{ $order->so_number }}</span>
                                                <span>{{ \Carbon\Carbon::parse($order->order_date)->format('d M Y') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @php
                                        $orderedQty = $order->items->sum('qty');
                                        $fulfilledQty = $order->fulfillments->sum('scanned_qty');
                                        $progressPercent = $orderedQty > 0 ? min(100, round(($fulfilledQty / $orderedQty) * 100)) : 0;
                                    @endphp
                                    <div class="w-full sm:w-48 mt-1 sm:mt-0">
                                        <div class="flex justify-between text-[10px] font-medium mb-1">
                                            <span class="text-zinc-600 dark:text-zinc-400">Progres</span>
                                            <span class="text-zinc-900 dark:text-zinc-100">{{ $fulfilledQty }} / {{ $orderedQty }} ({{ $progressPercent }}%)</span>
                                        </div>
                                        <div class="h-1.5 w-full bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            <div class="h-full {{ $progressPercent === 100 ? 'bg-emerald-500' : 'bg-blue-500' }} transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                                        </div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="card-status-overlay">
                                    @php
                                        $col = $this->activeColumns[$order->status] ?? null;
                                        $color = $col ? $col['color'] : 'zinc';
                                    @endphp
                                    <flux:badge size="sm" color="{{ $color }}">{{ $col['title'] ?? ucfirst($order->status) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center justify-end gap-2 pr-2 sm:pr-4 mt-1 sm:mt-0">
                                        @if($order->status === 'processing')
                                            <flux:button variant="primary" size="sm" wire:click.stop="$dispatch('open-fulfillment-modal', { orderId: {{ $order->id }} })">
                                                Proses
                                            </flux:button>
                                        @elseif($order->status === 'packing')
                                            <div class="flex gap-2">
                                                <flux:button variant="primary" size="sm" class="!bg-purple-600 hover:!bg-purple-700 !text-white border-none" wire:click.stop="$dispatch('open-packing-modal', { orderId: {{ $order->id }} })">
                                                    Packing
                                                </flux:button>
                                                @php
                                                    $gudangHandlesShipping = \Illuminate\Support\Facades\Cache::remember('setting_gudang_handles_shipping', 3600, function () {
                                                        return \App\Models\Setting::where('key', 'gudang_handles_shipping')->value('value');
                                                    }) == '1';
                                                @endphp
                                                @if($order->is_packed && $gudangHandlesShipping)
                                                    @if($order->courier_vendor_id)
                                                        <flux:button variant="primary" size="sm" class="!bg-blue-600 hover:!bg-blue-700 !text-white border-none" wire:click.stop="markAsShipped({{ $order->id }})">
                                                            Diambil
                                                        </flux:button>
                                                    @else
                                                        <flux:button variant="subtle" size="sm" wire:click.stop="$dispatch('open-shipping-modal', { orderId: {{ $order->id }} })">
                                                            Ekspedisi
                                                        </flux:button>
                                                    @endif
                                                @endif
                                            </div>
                                        @elseif($order->status === 'shipping')
                                            <flux:button variant="subtle" size="sm" wire:click.stop="$dispatch('open-shipping-modal', { orderId: {{ $order->id }} })">
                                                Resi
                                            </flux:button>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center py-8 text-zinc-500">
                                    <flux:icon.inbox class="w-8 h-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                                    Tidak ada data pengiriman.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    @endif
                </flux:table.rows>
            </flux:table>

            @if($this->tableDeliveries && $this->tableDeliveries->hasPages())
                <div class="mt-4">
                    {{ $this->tableDeliveries->links() }}
                </div>
            @endif
        </x-table.wrapper>
    </x-slot:table_layout>
</x-kanban.board>

    {{-- Modals --}}
    <div wire:ignore>
        <livewire:sales-order.item-fulfillment-modal />
        <livewire:sales-order.fulfillment-modal />
        <livewire:sales-order.packing-modal />
        <livewire:sales-order.shipping-modal />
    </div>
    
    <livewire:global.vendor-gallery-modal />
    <livewire:global.vendor-form-modal />
    
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
    .vertical-text {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
    }
</style>
</div>
