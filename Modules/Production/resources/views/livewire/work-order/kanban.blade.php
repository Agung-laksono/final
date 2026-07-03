<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Production\Models\ProductionOrder;

layout('layouts.app');
title('Kanban Produksi');

state([
    'columns' => [
        'material_fulfillment' => ['title' => 'Pemenuhan Bahan', 'color' => 'orange'],
        'waiting_vendor' => ['title' => 'Antrean Maklon', 'color' => 'cyan'],
        'in_production' => ['title' => 'Diproses Vendor', 'color' => 'blue'],
        'receiving' => ['title' => 'Penerimaan Gudang', 'color' => 'purple'],
        'completed' => ['title' => 'Selesai', 'color' => 'emerald'],
        'archived' => ['title' => 'Arsip', 'color' => 'zinc'],
    ],
    'search' => '',
    'selectedOrders' => [],
    'viewModeMaklon' => 'grouped', // 'grouped' or 'list'
    'validationErrors' => [],
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

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Kanban Produksi</flux:heading>
            <flux:subheading>Pantau dan kelola seluruh antrean pekerjaan (Work Orders).</flux:subheading>
        </div>
        <div class="w-full md:w-64">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari PO, Ref, atau Barang..." />
        </div>
    </div>

    <div class="flex justify-start gap-6 overflow-x-auto pb-4 h-[calc(100vh-12rem)] snap-x custom-scrollbar">
        @foreach($columns as $statusKey => $column)
            <div wire:key="kanban-column-{{ $statusKey }}" class="w-80 flex-shrink-0 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col h-full snap-center">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900 rounded-t-xl">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $column['title'] }}</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($statusKey === 'waiting_vendor' && count($this->selectedOrders) > 0)
                            <flux:button size="sm" variant="primary" icon="plus" wire:click="$dispatch('open-maklon-modal', { orderIds: {{ json_encode($this->selectedOrders) }} })">Buat SPK Maklon</flux:button>
                        @endif
                        @if($statusKey === 'in_production')
                            <div class="flex items-center bg-zinc-100 dark:bg-zinc-800 p-0.5 rounded-lg">
                                <button wire:click="$set('viewModeMaklon', 'grouped')" class="p-1 rounded-md text-xs {{ $viewModeMaklon === 'grouped' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white font-medium' : 'text-zinc-500 hover:text-zinc-700' }}" title="Mode Wadah">
                                    <flux:icon.rectangle-group class="w-4 h-4" />
                                </button>
                                <button wire:click="$set('viewModeMaklon', 'list')" class="p-1 rounded-md text-xs {{ $viewModeMaklon === 'list' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white font-medium' : 'text-zinc-500 hover:text-zinc-700' }}" title="Mode Eceran">
                                    <flux:icon.list-bullet class="w-4 h-4" />
                                </button>
                            </div>
                        @endif
                        <flux:badge size="sm">{{ count($this->orders[$statusKey] ?? []) }}</flux:badge>
                    </div>
                </div>
                
                <div class="flex-1 p-3 overflow-y-auto space-y-3 custom-scrollbar">
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
                                            <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $po->po_number ?? 'Tanpa SPK' }}</span>
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
