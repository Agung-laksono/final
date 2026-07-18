<?php
use function Livewire\Volt\{state, layout, title, computed, on, mount, usesPagination};
use Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Str;

usesPagination(theme: 'tailwind');

layout('layouts.app');
title('Pemenuhan Produksi (Gudang)');

state([
    'columns' => [
        'material_fulfillment' => ['title' => 'Menunggu Penyerahan', 'color' => 'amber'],
        'waiting_material' => ['title' => 'Kekurangan Bahan', 'color' => 'red'],
    ],
    'search' => '',
    'viewMode' => session('fulfillment_view_mode', 'kanban'),
    'groupBy' => session('fulfillment_group_by', 'wo'),
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

$setViewMode = function ($mode) {
    $this->viewMode = $mode;
    session(['fulfillment_view_mode' => $mode]);
};

$setGroupBy = function ($group) {
    $this->groupBy = $group;
    session(['fulfillment_group_by' => $group]);
};

$getBaseQuery = function () {
    $query = ProductionOrder::with(['item', 'item.type', 'creator'])->whereIn('status', ['material_fulfillment', 'waiting_material'])->latest();
    if ($this->search) {
        $query->where('order_number', 'like', '%' . $this->search . '%')
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
        $limit = $this->columnLimits[$status] ?? 10;
        
        $query = clone $this->getBaseQuery();
        
        $statusIds = $query->where('status', $status)
                           ->limit($limit)
                           ->pluck('id')
                           ->toArray();
                           
        $ids = array_merge($ids, $statusIds);
    }
    
    if (empty($ids)) return collect();
    
    $result = ProductionOrder::with(['item', 'item.type', 'creator'])
        ->whereIn('id', $ids)
        ->get()
        ->sortBy(function($order) use ($ids) {
            return array_search($order->id, $ids);
        });
        
    if ($this->groupBy === 'wo') {
        return $result->groupBy(function($order) {
            return $order->status ?? 'material_fulfillment';
        });
    }
    
    // Group by Raw Material logic
    $groupedByColumn = [];
    foreach ($this->columns as $status => $col) {
        $columnOrders = $result->where('status', $status);
        $materialMap = [];
        
        foreach ($columnOrders as $order) {
            $recipeItems = [];
            if (!empty($order->custom_bom)) {
                $customItems = json_decode($order->custom_bom, true) ?? [];
                foreach ($customItems as $c) {
                    $itemModel = \Modules\Inventory\Models\Item::find($c['item_id']);
                    if ($itemModel) {
                        $recipeItems[] = (object)[
                            'item_id' => $c['item_id'],
                            'qty' => $c['qty'],
                            'item' => $itemModel
                        ];
                    }
                }
            } else {
                $recipe = \Modules\Production\Models\ProductionRecipe::with('items.item')->where('item_id', $order->item_id)->where('is_active', true)->first();
                if ($recipe) {
                    $recipeItems = $recipe->items;
                }
            }
            
            foreach ($recipeItems as $ri) {
                $itemId = $ri->item_id;
                $needed = $ri->qty * $order->requested_qty;
                
                $alreadyConsumed = abs(\Illuminate\Support\Facades\DB::table('stock_movements')
                    ->where('reference_number', $order->order_number)
                    ->where('item_id', $itemId)
                    ->where('type', 'out')
                    ->sum('quantity') ?? 0);
                
                if (!isset($materialMap[$itemId])) {
                    $itemModel = $ri->item ?? \Modules\Inventory\Models\Item::with('unit')->find($itemId);
                    if (!$itemModel) continue;
                    
                    $materialMap[$itemId] = [
                        'item_id' => $itemId,
                        'item' => $itemModel,
                        'requested_qty' => 0,
                        'fulfilled_qty' => 0,
                        'wos' => [],
                        'status' => $status,
                    ];
                }
                
                $materialMap[$itemId]['requested_qty'] += $needed;
                $materialMap[$itemId]['fulfilled_qty'] += $alreadyConsumed;
                $materialMap[$itemId]['wos'][] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'qty' => $needed,
                    'fulfilled' => $alreadyConsumed,
                    'product_name' => $order->item->name ?? 'N/A',
                    'product_sku' => $order->item->code ?? 'N/A',
                    'unit' => $order->item->unit->name ?? 'pcs',
                ];
            }
        }
        
        $groupedByColumn[$status] = collect(array_values($materialMap));
    }
    
    return collect($groupedByColumn);
});

$tableOrders = computed(function () {
    $query = ProductionOrder::with(['item', 'item.type'])->whereIn('status', ['material_fulfillment', 'waiting_material'])->latest();
    
    if ($this->search) {
        $query->where('order_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('item', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    
    return $query->paginate($this->perPage);
});

on([
    'status-updated' => function () {},
    'echo:kanban.production_order,KanbanUpdated' => function () {},
]);
?>

<div class="w-full bg-transparent relative">

    <div wire:key="view-kanban-wrapper" class="w-full h-full relative {{ $this->viewMode === 'kanban' ? 'flex flex-col' : 'hidden' }}">
        <x-kanban.board 
            componentId="fulfillment"
            searchModel="search"
            searchPlaceholder="Cari WO atau Barang...">
            
            <x-slot:actions>
                <div class="flex items-center gap-1.5 sm:gap-2">
                    {{-- Group By Switcher --}}
                    <div class="flex border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden shrink-0">
                        <button type="button" wire:click="setGroupBy('wo')" wire:loading.attr="disabled" class="p-1 sm:p-1.5 px-2 sm:px-2.5 text-xs sm:text-[11px] font-bold transition-colors flex items-center gap-1 {{ $groupBy === 'wo' ? 'bg-indigo-600 dark:bg-indigo-500 text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 bg-white dark:bg-zinc-900' }}" title="Kelompokkan berdasarkan SPK / Work Order">
                            <flux:icon.arrow-path wire:loading wire:target="setGroupBy('wo')" class="w-3 h-3 animate-spin shrink-0" />
                            <span wire:loading.remove wire:target="setGroupBy('wo')" class="sm:hidden">WO</span>
                            <span wire:loading.remove wire:target="setGroupBy('wo')" class="hidden sm:inline">SPK (WO)</span>
                            <span wire:loading wire:target="setGroupBy('wo')" class="hidden sm:inline text-[9px] font-normal opacity-85"></span>
                        </button>
                        <button type="button" wire:click="setGroupBy('material')" wire:loading.attr="disabled" class="p-1 sm:p-1.5 px-2 sm:px-2.5 text-xs sm:text-[11px] font-bold transition-colors flex items-center gap-1 {{ $groupBy === 'material' ? 'bg-indigo-600 dark:bg-indigo-500 text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 bg-white dark:bg-zinc-900' }}" title="Kelompokkan berdasarkan Kebutuhan Bahan Baku">
                            <flux:icon.arrow-path wire:loading wire:target="setGroupBy('material')" class="w-3 h-3 animate-spin shrink-0" />
                            <span wire:loading.remove wire:target="setGroupBy('material')" class="sm:hidden">Bahan</span>
                            <span wire:loading.remove wire:target="setGroupBy('material')" class="hidden sm:inline">Bahan Baku</span>
                            <span wire:loading wire:target="setGroupBy('material')" class="hidden sm:inline text-[9px] font-normal opacity-85"></span>
                        </button>
                    </div>

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
                </div>
            </x-slot:actions>
            
            @foreach($columns as $statusKey => $column)
                @php
                    $defaultCollapsed = false;
                @endphp
                <x-kanban.column 
                    :statusKey="$statusKey" 
                    :column="$column" 
                    :componentId="'fulfillment'" 
                    :count="count($this->orders[$statusKey] ?? [])"
                    :defaultCollapsed="$defaultCollapsed"
                >
                        @forelse($this->orders[$statusKey] ?? [] as $order)
                            @if($groupBy === 'wo')
                                <div wire:key="kanban-order-{{ $order->id }}" 
                                     x-data="{ expanded: false, isDesktop: window.matchMedia('(hover: hover)').matches, loading: false }"
                                     @mouseenter="if(isDesktop) expanded = true"
                                     @mouseleave="if(isDesktop) expanded = false"
                                     @click.outside="if(!isDesktop) expanded = false"
                                     @open-fulfillment-modal.window="if($event.detail.orderId === {{ $order->id }}) loading = true"
                                     @modal-closed.window="loading = false"
                                     @status-updated.window="loading = false"
                                     class="bg-white dark:bg-zinc-800 rounded-xl p-2 shadow-sm border border-zinc-200 dark:border-zinc-700 hover:shadow-md hover:-translate-y-1 hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-all duration-300 active:scale-[0.98] cursor-pointer group flex flex-col gap-0.5 relative overflow-hidden" 
                                     :class="{ 'animate-pulse opacity-60 pointer-events-none': loading }"
                                     x-on:click="if(isDesktop) { $dispatch('open-fulfillment-modal', { orderId: {{ $order->id }} }); } else { expanded = !expanded; }"
                                     x-on:dblclick="if(!isDesktop) { $dispatch('open-fulfillment-modal', { orderId: {{ $order->id }} }); }">
                                    
                                    {{-- Status Marker Line --}}
                                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-{{ $column['color'] }}-400/50"></div>
                                    
                                    <div class="flex items-center justify-between pl-1.5 gap-2">
                                        <span class="text-[9px] sm:text-[10px] font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-1.5 py-0.5 rounded shrink-0">{{ $order->order_number }}</span>
                                        <span class="text-[8px] sm:text-[9px] text-zinc-400 truncate text-right" title="{{ $order->created_at->format('d M Y H:i') }}">
                                            {{ $order->created_at->format('d M') }} ({{ str_replace(['yang lalu', 'yang'], ['lalu', ''], $order->created_at->locale('id')->diffForHumans()) }})
                                        </span>
                                    </div>
                                    
                                    @php
                                        $recipeItems = [];
                                        if (!empty($order->custom_bom)) {
                                            $customItems = json_decode($order->custom_bom, true) ?? [];
                                            foreach ($customItems as $c) {
                                                $itemModel = \Modules\Inventory\Models\Item::with('unit')->find($c['item_id']);
                                                if ($itemModel) {
                                                    $recipeItems[] = (object)[
                                                        'qty' => $c['qty'] * $order->requested_qty,
                                                        'item' => $itemModel
                                                    ];
                                                }
                                            }
                                        } else {
                                            $recipe = \Modules\Production\Models\ProductionRecipe::with('items.item.unit')
                                                ->where('item_id', $order->item_id)
                                                ->where('is_active', 1)
                                                ->first();
                                            if ($recipe) {
                                                foreach ($recipe->items as $ri) {
                                                    $recipeItems[] = (object)[
                                                        'qty' => $ri->qty * $order->requested_qty,
                                                        'item' => $ri->item
                                                    ];
                                                }
                                            }
                                        }
                                        $skuCount = count($recipeItems);
                                        
                                        // Hitung progress pemenuhan bahan baku
                                        $totalNeeded = 0;
                                        $totalFulfilled = 0;
                                        foreach ($recipeItems as $ri) {
                                            $totalNeeded += $ri->qty;
                                            $consumed = abs(\Illuminate\Support\Facades\DB::table('stock_movements')
                                                ->where('reference_number', $order->order_number)
                                                ->where('item_id', $ri->item->id)
                                                ->where('type', 'out')
                                                ->sum('quantity') ?? 0);
                                            // Cap fulfilled at needed so over-fulfillment doesn't inflate percentage
                                            $totalFulfilled += min($consumed, $ri->qty);
                                        }
                                        $materialProgress = $totalNeeded > 0 ? round(($totalFulfilled / $totalNeeded) * 100) : 0;
                                    @endphp
                                    
                                    <div class="flex items-center gap-2 pl-1.5">
                                        @if($order->item->image)
                                            <flux:avatar src="{{ Storage::url($order->item->image) }}" loading="lazy" fallback="{{ substr($order->item->name ?? '?', 0, 2) }}" class="w-10 h-10 rounded-md shrink-0 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 transition-transform duration-300 group-active:scale-95 cursor-zoom-in hover:scale-105 hover:shadow-md" @click.stop="$dispatch('open-lightbox', { url: '{{ Storage::url($order->item->image) }}' })" />
                                        @else
                                            <flux:avatar fallback="{{ substr($order->item->name ?? '?', 0, 2) }}" class="w-10 h-10 rounded-md shrink-0 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 transition-transform duration-300 group-active:scale-95" />
                                        @endif
                                        
                                        <div class="flex flex-col flex-1 min-w-0 justify-between h-full py-0.5">
                                            <div class="flex justify-between items-start gap-2">
                                                <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs leading-tight truncate pr-2" title="{{ $order->item->name }}">{{ $order->item->name }}</h4>
                                                <div class="text-[9px] text-zinc-400 dark:text-zinc-500 flex items-center gap-1 shrink-0" title="Dibuat oleh {{ $order->creator->name ?? 'System' }}">
                                                    <flux:avatar src="{{ $order->creator?->avatarUrl() }}" fallback="{{ substr($order->creator->name ?? '?', 0, 2) }}" class="w-4 h-4 rounded-full border border-zinc-100 dark:border-zinc-700 shadow-sm" />
                                                    <span class="truncate max-w-[50px] font-medium">{{ explode(' ', $order->creator->name ?? 'System')[0] }}</span>
                                                </div>
                                            </div>
                                            
                                            <!-- Progress Bar Bahan Baku -->
                                            <div class="mt-2 flex flex-col gap-1" title="Progress penyerahan bahan baku untuk WO ini">
                                                <div class="flex justify-between items-baseline text-[10px] font-black">
                                                    <div>
                                                        <span class="{{ $materialProgress >= 100 ? 'text-emerald-600 dark:text-emerald-500' : 'text-amber-500 dark:text-amber-400' }}">{{ $totalFulfilled }}</span>
                                                        <span class="text-zinc-400 font-medium px-0.5">/</span>
                                                        <span class="text-emerald-600 dark:text-emerald-500">{{ $totalNeeded }}</span>
                                                        <span class="uppercase text-[8px] font-bold tracking-wider text-zinc-400 ml-0.5">Item Bahan</span>
                                                    </div>
                                                    <span class="text-[9px] font-bold {{ $materialProgress >= 100 ? 'text-emerald-600' : 'text-indigo-600' }}">{{ $materialProgress }}%</span>
                                                </div>
                                                <div class="w-full h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden flex">
                                                    <div class="h-full {{ $materialProgress >= 100 ? 'bg-emerald-500' : 'bg-indigo-500' }} rounded-full transition-all duration-500" style="width: {{ $materialProgress }}%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {{-- Bahan Baku breakdown --}}
                                    <div x-show="expanded" x-collapse.duration.300ms>
                                        <div class="pl-1.5 pt-2 mt-1 border-t border-zinc-100 dark:border-zinc-700/50 flex flex-col gap-1.5 text-[10px]">
                                            @foreach($recipeItems as $ri)
                                                @if($ri->item)
                                                    <div class="flex items-center justify-between p-1.5 rounded bg-zinc-50 dark:bg-zinc-900/40 border border-zinc-200/50 dark:border-zinc-800/50">
                                                        <div class="flex flex items-center justify-start pl-1">
                                                            <span class="font-bold text-zinc-800 dark:text-zinc-200 truncate">{{ $ri->item->name }}</span>
                                                            agung
                                                        </div>
                                                        <span class="text-[8px] text-zinc-400 font-mono">{{ $ri->item->code }}</span>
                                                        <span class="text-zinc-700 dark:text-zinc-300 font-mono text-[9px] shrink-0 font-bold">{{ $ri->qty }} {{ $ri->item->unit->name ?? 'pcs' }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @else
                                {{-- Group by Material View --}}
                                <div wire:key="kanban-material-{{ $statusKey }}-{{ $order['item_id'] }}" 
                                     x-data="{ expanded: false, isDesktop: window.matchMedia('(hover: hover)').matches, loading: false }"
                                     @mouseenter="if(isDesktop) expanded = true"
                                     @mouseleave="if(isDesktop) expanded = false"
                                     @click.outside="if(!isDesktop) expanded = false"
                                     @open-material-fulfillment-modal.window="if($event.detail.itemId === {{ $order['item_id'] }} && '{{ $statusKey }}' === '{{ $order['status'] }}') loading = true"
                                     @modal-closed.window="loading = false"
                                     @status-updated.window="loading = false"
                                     class="bg-white dark:bg-zinc-800 rounded-xl p-2 shadow-sm border border-zinc-200 dark:border-zinc-700 hover:shadow-md hover:-translate-y-1 hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-all duration-300 active:scale-[0.98] cursor-pointer group flex flex-col gap-1.5 relative overflow-hidden" 
                                     :class="{ 'animate-pulse opacity-60 pointer-events-none': loading }"
                                     x-on:click="if(isDesktop) { $dispatch('open-material-fulfillment-modal', { itemId: {{ $order['item_id'] }} }); } else { expanded = !expanded; }"
                                     x-on:dblclick="if(!isDesktop) { $dispatch('open-material-fulfillment-modal', { itemId: {{ $order['item_id'] }} }); }">
                                     
                                    {{-- Status Marker Line --}}
                                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-{{ $column['color'] }}-400/50"></div>
                                    
                                    <div class="flex items-center gap-2 pl-1.5">
                                        @if($order['item']->image)
                                            <flux:avatar src="{{ Storage::url($order['item']->image) }}" loading="lazy" fallback="{{ substr($order['item']->name ?? '?', 0, 2) }}" class="w-10 h-10 rounded-md shrink-0 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 transition-transform duration-300 group-active:scale-95 cursor-zoom-in hover:scale-105 hover:shadow-md" @click.stop="$dispatch('open-lightbox', { url: '{{ Storage::url($order['item']->image) }}' })" />
                                        @else
                                            <flux:avatar fallback="{{ substr($order['item']->name ?? '?', 0, 2) }}" class="w-10 h-10 rounded-md shrink-0 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 transition-transform duration-300 group-active:scale-95" />
                                        @endif
                                        
                                        <div class="flex flex-col flex-1 min-w-0">
                                            <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-xs leading-tight truncate pr-2" title="{{ $order['item']->name }}">{{ $order['item']->name }}</h4>
                                            <div class="flex items-baseline gap-1 mt-0.5">
                                                <div class="text-[10px] font-black">
                                                    <span class="text-red-500 dark:text-red-400">{{ $order['fulfilled_qty'] }}</span>
                                                    <span class="text-zinc-400 font-medium">/</span>
                                                    <span class="text-emerald-600 dark:text-emerald-500">{{ $order['requested_qty'] }}</span>
                                                </div>
                                                <span class="uppercase text-[8px] font-bold tracking-wider text-zinc-400 mr-2">{{ $order['item']->unit->name ?? 'pcs' }}</span>
                                                <span class="text-[9px] text-zinc-500 dark:text-zinc-400 font-medium border-l border-zinc-200 dark:border-zinc-700 pl-2 shrink-0">
                                                    {{ count($order['wos']) }} WO
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {{-- WOs breakdown --}}
                                    <div x-show="expanded" x-collapse.duration.300ms>
                                        <div class="pl-1.5 pt-2 mt-1 border-t border-zinc-100 dark:border-zinc-700/50 flex flex-col gap-1.5 text-[10px]">
                                            @foreach($order['wos'] as $wo)
                                                <button type="button" @click.stop="$dispatch('open-fulfillment-modal', { orderId: {{ $wo['order_id'] }} })" class="flex flex-col text-left p-1.5 rounded bg-zinc-50 dark:bg-zinc-900/40 hover:bg-zinc-100 dark:hover:bg-zinc-950 transition-all duration-300 border border-zinc-200/50 dark:border-zinc-800/50 active:scale-[0.98] w-full cursor-pointer" title="Buka Detail Penyerahan">
                                                    <div class="flex items-center justify-between font-bold text-indigo-600 dark:text-indigo-400">
                                                        <span>{{ $wo['order_number'] }}</span>
                                                        <span class="text-zinc-700 dark:text-zinc-300 font-mono text-[9px]">{{ $wo['qty'] }} {{ $wo['unit'] }}</span>
                                                    </div>
                                                    <div class="text-[9px] text-zinc-500 dark:text-zinc-400 truncate mt-0.5" title="{{ $wo['product_name'] }}">
                                                        {{ $wo['product_name'] }} <span class="text-zinc-400 font-mono text-[8px]">({{ $wo['product_sku'] }})</span>
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="h-24 flex flex-col items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-xs text-zinc-400 dark:text-zinc-500 gap-2">
                                <flux:icon.clipboard-document-check class="w-6 h-6 text-zinc-300 dark:text-zinc-600" />
                                Kosong
                            </div>
                        @endforelse
                        
                        @if(count($this->orders[$statusKey] ?? []) >= ($columnLimits[$statusKey] ?? 10))
                            <x-kanban.load-more 
                                :statusKey="$statusKey" 
                            />
                        @endif
                </x-kanban.column>
            @endforeach
        </x-kanban.board>
    </div>

    {{-- Table View --}}
    <div wire:key="view-table-wrapper" class="w-full {{ $this->viewMode === 'table' ? 'block' : 'hidden' }}">
        <x-table.header searchModel="search" searchPlaceholder="Cari WO atau Barang...">
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
                {{-- Desktop Table --}}
                <div class="hidden md:block">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>No. WO</flux:table.column>
                            <flux:table.column>Barang yang Diproduksi</flux:table.column>
                            <flux:table.column>Target Produksi</flux:table.column>
                            <flux:table.column>Status Bahan</flux:table.column>
                        </flux:table.columns>
        
                        <flux:table.rows>
                            @forelse($this->tableOrders as $order)
                                <flux:table.row wire:key="table-order-{{ $order->id }}" class="cursor-pointer hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50 transition-colors" x-on:click="$dispatch('open-fulfillment-modal', { orderId: {{ $order->id }} })">
                                    <flux:table.cell>
                                        <span class="text-sm font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded">{{ $order->order_number }}</span>
                                        <div class="text-[11px] text-zinc-500 mt-1.5 flex items-center gap-1 font-medium">
                                            <flux:icon.clock class="w-3 h-3 text-zinc-400" />
                                            {{ $order->created_at->format('d M Y') }}
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex items-center gap-3">
                                            <flux:avatar src="{{ $order->item->image ? Storage::url($order->item->image) : '' }}" fallback="{{ substr($order->item->name ?? '?', 0, 2) }}" size="sm" class="shrink-0 rounded-md ring-1 ring-zinc-200 dark:ring-zinc-700" />
                                            <div class="flex flex-col min-w-0">
                                                <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100 truncate">{{ $order->item->name }}</span>
                                                <span class="text-[11px] text-zinc-500 font-mono flex items-center gap-1 mt-0.5">
                                                    <flux:icon.qr-code class="w-3 h-3" />
                                                    {{ $order->item->code ?? '-' }}
                                                </span>
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <span class="font-bold text-zinc-800 dark:text-zinc-200 text-base">{{ $order->requested_qty }}</span> <span class="text-xs font-medium text-zinc-500 uppercase tracking-wider">{{ $order->item->unit->name ?? 'pcs' }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if($order->status === 'waiting_material')
                                            <flux:badge size="sm" color="red">Kurang Bahan</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="amber">Menunggu Bahan</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4">
                                        <div class="flex flex-col items-center justify-center py-12 text-zinc-500">
                                            <flux:icon.clipboard-document-check class="w-12 h-12 mb-3 text-zinc-300 dark:text-zinc-600" />
                                            <p>Tidak ada pesanan produksi yang mengantre bahan.</p>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>

                {{-- Mobile Card List --}}
                <div class="md:hidden flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->tableOrders as $order)
                        <div class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors cursor-pointer" x-on:click="$dispatch('open-fulfillment-modal', { orderId: {{ $order->id }} })">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded">{{ $order->order_number }}</span>
                                @if($order->status === 'waiting_material')
                                    <flux:badge size="sm" color="red">Kurang</flux:badge>
                                @else
                                    <flux:badge size="sm" color="amber">Menunggu</flux:badge>
                                @endif
                            </div>
                            <div class="flex gap-3 mb-4 items-center">
                                <flux:avatar src="{{ $order->item->image ? Storage::url($order->item->image) : '' }}" fallback="{{ substr($order->item->name ?? '?', 0, 2) }}" size="md" class="shrink-0 rounded-lg shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700" />
                                <div class="flex flex-col min-w-0 flex-1">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mb-1 leading-snug truncate">{{ $order->item->name }}</div>
                                    <div class="text-sm text-zinc-500 flex items-center gap-1.5 flex-wrap">
                                        <span>Target: <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $order->requested_qty }}</span> <span class="text-[10px] uppercase">{{ $order->item->unit->name ?? 'pcs' }}</span></span>
                                        <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                                        <span class="text-xs text-zinc-400">{{ $order->created_at->format('d M') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-zinc-500">Tidak ada pesanan produksi yang mengantre bahan.</div>
                    @endforelse
                </div>

                <div class="mt-4 mb-8">
                    {{ $this->tableOrders->links() }}
                </div>
        </x-table.wrapper>
    </div>
    
    {{-- MODAL WRAPPER DENGAN WIRE:IGNORE --}}
    {{-- Memastikan Livewire Morphdom tidak menghancurkan struktur Modal saat Kanban merender ulang --}}
    <div wire:ignore>
        <livewire:work-order.fulfillment-modal />
        <livewire:material-fulfillment-modal />
    </div>
</div>
