<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Production\Models\ProductionOrder;

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
]);

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

<div class="kanban-root relative flex flex-col w-full" 
     x-data="{ 
        showHeader: $persist(true).as('kanban-prod-header-user-{{ auth()->id() }}'),
        transparent: $persist(false).as('kanban-prod-transparent-user-{{ auth()->id() }}')
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

        .vertical-text {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
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
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari PO, Ref, atau Barang..." />
        </div>

        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
            <div class="hidden sm:flex items-center mr-2" title="Mode Transparan">
                <flux:switch x-model="transparent" label="Transparan" />
            </div>

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
                                <button wire:click="$set('viewModeMaklon', 'list')" class="p-1 rounded-md text-xs {{ $viewModeMaklon === 'list' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white font-medium' : 'text-zinc-500 hover:text-zinc-700' }}" title="Mode Eceran">
                                    <flux:icon.list-bullet class="w-4 h-4" />
                                </button>
                            </div>
                        @endif
                        <flux:badge size="sm" class="shrink-0">{{ count($this->orders[$statusKey] ?? []) }}</flux:badge>
                        <button @click.stop="collapsed = !collapsed" class="text-zinc-400 hover:text-zinc-600 transition-colors shrink-0" x-bind:title="collapsed ? 'Buka Kolom' : 'Tutup Kolom'">
                            <flux:icon.arrows-right-left class="w-4 h-4" x-bind:class="collapsed ? 'rotate-90' : ''" />
                        </button>
                    </div>
                </div>
                
                <div x-show="!collapsed" x-transition.opacity.duration.300ms class="flex-1 p-3 overflow-y-auto space-y-3" :class="transparent ? 'hide-scroll' : 'custom-scrollbar'">
                    @if($statusKey === 'in_production' && $viewModeMaklon === 'grouped')
                        @php
                            $groupedByPo = collect($this->orders[$statusKey] ?? [])->groupBy('purchase_order_id');
                        @endphp
                        
                        @forelse($groupedByPo as $poId => $poOrders)
                            @php $po = $poOrders->first()->purchaseOrder; @endphp
                            <div wire:key="po-group-{{ $statusKey }}-{{ $poId }}" class="bg-zinc-100 dark:bg-zinc-800/40 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700">
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
                                <flux:button size="xs" variant="primary" class="w-full justify-center mt-1" wire:click="$dispatch('open-finish-phase-bulk-modal', { poId: {{ $poId }}, phase: 'maklon' })">
                                    &#x2714; Selesaikan Semua (1 SPK)
                                </flux:button>
                            </div>
                                
                                <div class="p-2 space-y-2">
                                    @foreach($poOrders as $order)
                                        <div wire:key="card-grouped-{{ $order->id }}">
                                            @include('production::livewire.work-order.partials.kanban-card', ['order' => $order, 'statusKey' => $statusKey, 'viewModeMaklon' => $viewModeMaklon])
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
            </div>
        </div>
    </div>

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
