<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Production\Models\ProductionOrder;

layout('layouts.app');
title('Kanban Produksi');

state([
    'columns' => [
        'pending_approval' => ['title' => 'Menunggu Persetujuan', 'color' => 'amber'],
        'material_fulfillment' => ['title' => 'Pemenuhan Bahan', 'color' => 'orange'],
        'waiting_vendor' => ['title' => 'Antre Maklon', 'color' => 'cyan'],
        'in_production' => ['title' => 'Sedang Diproduksi', 'color' => 'blue'],
        'receiving' => ['title' => 'Penerimaan Gudang', 'color' => 'purple'],
        'completed' => ['title' => 'Selesai', 'color' => 'emerald'],
        'archived' => ['title' => 'Arsip', 'color' => 'zinc'],
    ],
    'search' => '',
    'selectedOrders' => [],
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
    
    // Merge waiting_material ke dalam material_fulfillment
    if (isset($grouped['waiting_material'])) {
        $fulfillment = $grouped['material_fulfillment'] ?? collect();
        $grouped['material_fulfillment'] = $fulfillment->merge($grouped['waiting_material'])->sortByDesc('created_at');
        unset($grouped['waiting_material']);
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

$checkMaterialArrival = function ($orderId) {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $po = ProductionOrder::find($orderId);
    if (!$po) return;

    $recipe = \DB::table('production_recipes')->where('item_id', $po->item_id)->where('is_active', true)->first();
    
    // Jika tidak ada resep, langsung loloskan
    if (!$recipe) {
        $po->status = 'material_fulfillment';
        $po->save();
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        \Flux::toast('Lanjut ke Penyiapan Bahan.', variant: 'success');
        return;
    }

    $recipeItems = \DB::table('production_recipe_items')
        ->join('items', 'production_recipe_items.item_id', '=', 'items.id')
        ->where('production_recipe_id', $recipe->id)
        ->select('production_recipe_items.*', 'items.name')
        ->get();

    $deficitItems = [];

    foreach ($recipeItems as $ri) {
        $needed = $ri->qty * $po->requested_qty;
        
        $alreadyConsumed = \DB::table('stock_movements')
            ->where('reference_number', $po->order_number)
            ->where('item_id', $ri->item_id)
            ->where('type', 'out')
            ->sum('quantity') ?? 0;
            
        $remainingNeeded = max(0, $needed - $alreadyConsumed);
            
        $stock = \DB::table('item_warehouse')
            ->where('item_id', $ri->item_id)
            ->sum('stock') ?? 0;

        if ($stock < $remainingNeeded) {
            $deficitItems[] = "{$ri->name} (Butuh: {$remainingNeeded}, Fisik: {$stock})";
        }
    }

    if (count($deficitItems) > 0) {
        $msg = "Bahan fisik di gudang masih kurang! " . implode(', ', $deficitItems);
        \Flux::toast($msg, variant: 'danger');
        return;
    }

    // Jika lengkap
    $po->status = 'material_fulfillment';
    $po->save();
    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('production_order');
    \Flux::toast('Semua bahan fisik telah tervalidasi! Silakan masuk ke Penyiapan Bahan untuk memotong stok.', variant: 'success');
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
            <div class="w-80 flex-shrink-0 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col h-full snap-center">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900 rounded-t-xl">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $column['title'] }}</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($statusKey === 'waiting_vendor' && count($this->selectedOrders) > 0)
                            <flux:button size="sm" variant="primary" icon="plus" wire:click="$dispatch('open-maklon-modal', { orderIds: {{ json_encode($this->selectedOrders) }} })">Buat PO</flux:button>
                        @endif
                        <flux:badge size="sm">{{ count($this->orders[$statusKey] ?? []) }}</flux:badge>
                    </div>
                </div>
                
                <div class="flex-1 p-3 overflow-y-auto space-y-3 custom-scrollbar">
                    @forelse($this->orders[$statusKey] ?? [] as $order)
                        <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 hover:-translate-y-1 hover:shadow-md transition-all duration-300">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs font-mono bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded w-max">{{ $order->order_number }}</span>
                                    @if($order->status === 'waiting_material')
                                        <span class="text-[10px] font-semibold text-red-600 dark:text-red-400 flex items-center gap-1">
                                            <flux:icon.exclamation-triangle class="w-3 h-3" /> Kurang Bahan
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] text-zinc-500">{{ $order->created_at->format('d M') }}</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                @if($statusKey === 'waiting_vendor')
                                    <div class="pt-0.5">
                                        <flux:checkbox wire:click="toggleSelection({{ $order->id }})" :checked="in_array($order->id, $this->selectedOrders)" />
                                    </div>
                                @endif
                                <div class="font-bold text-sm text-zinc-900 dark:text-white mb-1">{{ $order->item->name }}</div>
                            </div>
                            <div class="flex gap-2 text-sm text-zinc-600 dark:text-zinc-400 mb-3">
                                <div>Target: <span class="font-bold text-blue-600">{{ $order->requested_qty }}</span></div>
                                @if($order->fulfilled_qty > 0)
                                    <div>Selesai: <span class="font-bold text-emerald-600">{{ $order->fulfilled_qty }}</span></div>
                                @endif
                            </div>
                            
                            @if($order->reference_number)
                                <div class="text-xs text-zinc-500 flex items-center gap-1 mb-2">
                                    <flux:icon.link class="w-3 h-3" /> Ref: {{ $order->reference_number }}
                                </div>
                            @endif
                            @if($order->purchaseOrder)
                                <div class="text-xs text-zinc-500 flex items-center gap-1 mb-2">
                                    <flux:icon.truck class="w-3 h-3" /> PO: {{ $order->purchaseOrder->po_number }}
                                </div>
                            @endif

                            <div class="flex flex-col gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                @if($statusKey === 'pending_approval')
                                    <flux:button size="sm" variant="primary" wire:click="updateStatus({{ $order->id }}, 'material_fulfillment')" class="w-full justify-center">Setujui Produksi</flux:button>
                                @elseif($statusKey === 'material_fulfillment')
                                    @if($order->status === 'waiting_material')
                                        <flux:button size="sm" variant="filled" wire:click="checkMaterialArrival({{ $order->id }})" class="w-full justify-center !bg-red-600 hover:!bg-red-700 text-white" tooltip="Validasi stok gudang secara Real-Time">Validasi Kedatangan Bahan</flux:button>
                                    @else
                                        <flux:button size="sm" variant="filled" wire:click="$dispatch('open-fulfillment-modal', { orderId: {{ $order->id }} })" class="w-full justify-center !bg-orange-600 hover:!bg-orange-700 text-white">Bahan Siap -> Produksi</flux:button>
                                    @endif
                                @elseif($statusKey === 'waiting_vendor')
                                    <flux:button size="sm" variant="subtle" wire:click="updateStatus({{ $order->id }}, 'material_fulfillment')" class="w-full justify-center text-zinc-500">Batal Maklon</flux:button>
                                @elseif($statusKey === 'in_production')
                                    <div class="flex gap-2">
                                        <flux:button size="sm" variant="subtle" wire:click="$dispatch('open-vendor-cost-modal', { orderId: {{ $order->id }} })" class="w-full justify-center" tooltip="Input Biaya Vendor/Maklon">
                                            <flux:icon.currency-dollar class="w-4 h-4" />
                                        </flux:button>
                                        <flux:button size="sm" variant="filled" wire:click="updateStatus({{ $order->id }}, 'receiving')" class="w-full justify-center !bg-blue-600 hover:!bg-blue-700 text-white">Selesai Produksi</flux:button>
                                    </div>
                                @elseif($statusKey === 'receiving')
                                    <flux:button size="sm" variant="filled" wire:click="$dispatch('open-receiving-modal', { orderId: {{ $order->id }} })" class="w-full justify-center !bg-purple-600 hover:!bg-purple-700 text-white">Lanjut Penerimaan</flux:button>
                                @elseif($statusKey === 'completed')
                                    <flux:button size="sm" variant="subtle" wire:click="updateStatus({{ $order->id }}, 'archived')" class="w-full justify-center text-zinc-500">Arsipkan</flux:button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400">
                            Kosong
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <livewire:work-order.fulfillment-modal />
    <livewire:work-order.maklon-modal />
    <livewire:work-order.vendor-cost-modal />
    <livewire:work-order.receiving-modal />
</div>

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
