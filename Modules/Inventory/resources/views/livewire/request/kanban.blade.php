<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Inventory\Models\InventoryRequest;
use Modules\Purchase\Models\PurchaseQueue;
use Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Str;

layout('layouts.app');
title('Pivot Permintaan Barang');

state([
    'columns' => [
        'draft' => ['title' => 'Permintaan Baru', 'color' => 'amber'],
        'review' => ['title' => 'Sedang Ditinjau', 'color' => 'blue'],
        'routed' => ['title' => 'Telah Dialihkan', 'color' => 'emerald'],
        'rejected' => ['title' => 'Ditolak', 'color' => 'red'],
    ],
    'search' => '',
]);

$requests = computed(function () {
    $query = InventoryRequest::with(['item', 'item.type'])->latest();
    if ($this->search) {
        $query->where('reference_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('item', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    return $query->get()->groupBy('status');
});

$routeToPurchase = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    
    $req = InventoryRequest::with('item')->find($requestId);
    if ($req && $req->status !== 'routed') {
        PurchaseQueue::create([
            'item_id' => $req->item_id,
            'source_type' => 'inventory_request',
            'requested_qty' => $req->requested_qty,
            'notes' => 'Dialihkan dari Pivot Gudang. Ref: ' . $req->reference_number . '. Notes: ' . $req->notes,
        ]);
        
        $req->status = 'routed';
        $req->routed_to = 'purchase';
        $req->routed_by = auth()->id();
        $req->save();
        
        \Flux::toast('Berhasil dialihkan ke Antrean Pembelian.', variant: 'success');
    }
};

$routeToProduction = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    
    $req = InventoryRequest::with('item')->find($requestId);
    if ($req && $req->status !== 'routed') {
        // Generate random order number for production
        $orderNumber = 'PROD-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        
        ProductionOrder::create([
            'order_number' => $orderNumber,
            'item_id' => $req->item_id,
            'requested_qty' => $req->requested_qty,
            'reference_number' => $req->reference_number,
            'notes' => 'Dialihkan dari Pivot Gudang. Notes: ' . $req->notes,
            'status' => 'pending_approval',
            'created_by' => auth()->id(),
        ]);
        
        $req->status = 'routed';
        $req->routed_to = 'production';
        $req->routed_by = auth()->id();
        $req->save();
        
        \Flux::toast('Berhasil dialihkan ke Antrean Produksi.', variant: 'success');
    }
};

$review = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    $req = InventoryRequest::find($requestId);
    if ($req) {
        $req->status = 'review';
        $req->save();
        \Flux::toast('Permintaan sedang ditinjau.', variant: 'success');
    }
};

$reject = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    $req = InventoryRequest::find($requestId);
    if ($req) {
        $req->status = 'rejected';
        $req->save();
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Pivot Permintaan Barang</flux:heading>
            <flux:subheading>Hub pusat penentuan alur defisit barang (Beli vs Produksi).</flux:subheading>
        </div>
        <div class="w-full md:w-64">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari barang atau ref..." />
        </div>
    </div>

    <div class="flex justify-start gap-6 overflow-x-auto pb-4 h-[calc(100vh-12rem)] snap-x">
        @foreach($columns as $statusKey => $column)
            <div class="w-80 flex-shrink-0 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col h-full snap-center">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900 rounded-t-xl">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $column['title'] }}</h3>
                    </div>
                    <flux:badge size="sm">{{ count($this->requests[$statusKey] ?? []) }}</flux:badge>
                </div>
                
                <div class="flex-1 p-3 overflow-y-auto space-y-3">
                    @forelse($this->requests[$statusKey] ?? [] as $req)
                        <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-mono bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">{{ $req->reference_number }}</span>
                                <flux:badge size="sm" color="{{ $req->item->type->name === 'Produk Jadi' ? 'purple' : 'blue' }}">{{ $req->item->type->name ?? 'Unknown' }}</flux:badge>
                            </div>
                            <div class="font-bold text-sm text-zinc-900 dark:text-white mb-1">{{ $req->item->name }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Butuh: <span class="font-bold text-red-600">{{ $req->requested_qty }}</span> {{ $req->item->unit->name ?? 'pcs' }}</div>
                            
                            @if($req->notes)
                                <div class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 p-2 rounded mb-3">{{ $req->notes }}</div>
                            @endif

                            @if($statusKey !== 'routed' && $statusKey !== 'rejected')
                                <div class="flex flex-col gap-2 mt-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                    @if($statusKey === 'draft')
                                        <flux:button size="sm" variant="subtle" wire:click="review({{ $req->id }})" class="w-full justify-center">Mulai Tinjau</flux:button>
                                    @endif
                                    <flux:button size="sm" variant="primary" wire:click="routeToPurchase({{ $req->id }})" class="w-full justify-center">Alihkan ke Pembelian</flux:button>
                                    <flux:button size="sm" variant="filled" wire:click="routeToProduction({{ $req->id }})" class="w-full justify-center !bg-purple-600 hover:!bg-purple-700 text-white">Alihkan ke Produksi</flux:button>
                                    @if($statusKey !== 'rejected')
                                        <flux:button size="sm" variant="danger" wire:click="reject({{ $req->id }})" class="w-full justify-center">Tolak</flux:button>
                                    @endif
                                </div>
                            @elseif($statusKey === 'routed')
                                <div class="text-xs font-medium text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 p-2 rounded text-center mt-2">
                                    Telah dialihkan ke: {{ strtoupper($req->routed_to) }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-sm text-zinc-400 py-4">Kosong</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
