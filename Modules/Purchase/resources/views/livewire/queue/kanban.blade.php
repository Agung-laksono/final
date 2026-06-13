<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Purchase\Models\PurchaseQueue;
use Modules\Inventory\Models\Item;

layout('layouts.app');
title('Kanban Permintaan Pembelian');

// Definisi Kolom Kanban
state([
    'columns' => [
        'pending_approval' => ['title' => 'Menunggu Persetujuan', 'color' => 'zinc'],
        'approved' => ['title' => 'Disetujui (Antre)', 'color' => 'blue'],
        'ordered' => ['title' => 'Sudah Dipesan (PO)', 'color' => 'amber'],
        'completed' => ['title' => 'Selesai / Ready', 'color' => 'emerald'],
        'rejected' => ['title' => 'Ditolak', 'color' => 'red'],
        'archived' => ['title' => 'Arsip', 'color' => 'slate'],
    ]
]);

$queues = computed(function () {
    return PurchaseQueue::with('item')->latest()->get()->groupBy(function($q) {
        return $q->status ?? 'pending_approval';
    });
});

$updateStatus = function ($queueId, $newStatus) {
    abort_unless(auth()->user()->can('purchase.update'), 403, 'Anda tidak memiliki izin untuk mengubah status antrean.');
    
    if (!array_key_exists($newStatus, $this->columns)) return;
    
    $queue = PurchaseQueue::find($queueId);
    if ($queue) {
        $queue->status = $newStatus;
        $queue->save();
        $this->dispatch('status-updated');
    }
};

$rejectQueue = function ($queueId) {
    abort_unless(auth()->user()->can('purchase.update'), 403, 'Anda tidak memiliki izin.');
    $queue = PurchaseQueue::find($queueId);
    if ($queue) {
        $queue->status = 'rejected';
        $queue->approved_qty = 0;
        $queue->save();
        $this->dispatch('status-updated');
    }
};

on(['status-updated' => function () {
    // Kosong saja, tujuannya hanya memancing re-render agar computed $queues dijalankan ulang
}]);

?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Kanban Permintaan Pembelian</flux:heading>
            <flux:subheading>Pantau alur dan status dari setiap antrean permintaan barang (Purchase Queue).</flux:subheading>
        </div>
        
        @can('purchase.create')
            <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-create-queue-modal')">Buat Permintaan Baru</flux:button>
        @endcan
    </div>

    {{-- Kanban Board Area --}}
    <div class="flex gap-6 overflow-x-auto pb-4 h-[calc(100vh-12rem)]">
        @foreach($columns as $statusKey => $column)
            <div class="flex-1 flex flex-col min-w-[320px] max-w-[320px] bg-zinc-50 dark:bg-zinc-800/30 rounded-2xl border border-zinc-200 dark:border-zinc-800/50 shadow-sm overflow-hidden" 
                 wire:key="column-{{ $statusKey }}">
                
                {{-- Column Header --}}
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900 rounded-t-xl">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500 shadow-[0_0_8px_rgba(0,0,0,0.5)] shadow-{{ $column['color'] }}-500/50"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $column['title'] }}</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($statusKey === 'approved' && count($this->queues['approved'] ?? []) > 0)
                            <flux:button size="xs" variant="primary" icon="document-plus" wire:click="$dispatch('open-consolidation-modal')" class="!h-6 !px-2 text-[10px]">Buat PO</flux:button>
                        @endif
                        <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800">{{ count($this->queues[$statusKey] ?? []) }}</flux:badge>
                    </div>
                </div>

                {{-- Column Items --}}
                <div class="flex-1 p-3 overflow-y-auto space-y-3 custom-scrollbar">
                    @forelse($this->queues[$statusKey] ?? [] as $queue)
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

                        <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 hover:-translate-y-1 hover:shadow-md hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-all duration-300 group relative flex flex-col">
                            
                            {{-- Header Card --}}
                            <div class="flex justify-between items-start mb-3">
                                <span class="font-mono text-[11px] font-bold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 px-2 py-0.5 rounded-md">
                                    #PQ-{{ str_pad($queue->id, 4, '0', STR_PAD_LEFT) }}
                                </span>
                                <div class="flex items-center text-[10px] text-zinc-400 font-medium">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    {{ $queue->created_at->diffForHumans() }}
                                </div>
                            </div>

                            {{-- Source Badge --}}
                            <div class="mb-2">
                                <span class="inline-flex items-center rounded-md bg-{{ $sourceBadge['color'] }}-50 dark:bg-{{ $sourceBadge['color'] }}-500/10 px-2 py-1 text-[10px] font-semibold text-{{ $sourceBadge['color'] }}-600 dark:text-{{ $sourceBadge['color'] }}-400 ring-1 ring-inset ring-{{ $sourceBadge['color'] }}-500/20">
                                    {!! $sourceBadge['icon'] !!}
                                    {{ $sourceBadge['label'] }}
                                </span>
                            </div>

                            {{-- Item Info --}}
                            <h4 class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 mb-1 leading-tight group-hover:text-{{ $column['color'] }}-600 dark:group-hover:text-{{ $column['color'] }}-400 transition-colors line-clamp-2">
                                {{ $queue->item->name ?? 'Barang Dihapus' }}
                            </h4>
                            
                            {{-- Qty Bottom --}}
                            <div class="flex items-end justify-between text-sm mt-3 pt-3 border-t border-dashed border-zinc-200 dark:border-zinc-700">
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-0.5">Jumlah Diperlukan</span>
                                    <span class="text-lg font-black text-zinc-800 dark:text-zinc-200 tracking-tight leading-none">{{ $queue->requested_qty }} <span class="text-xs font-medium text-zinc-500">Unit</span></span>
                                </div>
                                @if($queue->approved_qty !== null && $queue->status !== 'rejected')
                                    @if($queue->approved_qty < $queue->requested_qty)
                                        <div class="flex flex-col text-right" title="Hanya disetujui sebagian">
                                            <span class="text-[10px] font-bold text-amber-500 uppercase tracking-wider mb-0.5">Disetujui</span>
                                            <span class="text-sm font-bold text-amber-600 dark:text-amber-400 tracking-tight leading-none">{{ $queue->approved_qty }} Unit</span>
                                        </div>
                                    @elseif($queue->approved_qty > $queue->requested_qty)
                                        <div class="flex flex-col text-right" title="Disetujui lebih dari permintaan">
                                            <span class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-0.5">Disetujui Ekstra</span>
                                            <span class="text-sm font-bold text-blue-600 dark:text-blue-400 tracking-tight leading-none">+{{ $queue->approved_qty }} Unit</span>
                                        </div>
                                    @endif
                                @endif
                                @if($queue->status === 'rejected')
                                    <div class="flex flex-col text-right">
                                        <span class="text-[10px] font-bold text-red-500 uppercase tracking-wider mb-0.5">Disetujui</span>
                                        <span class="text-sm font-bold text-red-600 dark:text-red-400 tracking-tight leading-none">0 Unit</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Action Buttons for Pending Approval --}}
                            @if($statusKey === 'pending_approval')
                                <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800 flex gap-2 w-full">
                                    <flux:button variant="danger" size="sm" class="flex-1 text-[11px]" @click.stop="if(confirm('Tolak permintaan ini?')) { $wire.rejectQueue({{ $queue->id }}) }">Tolak</flux:button>
                                    <flux:button variant="primary" size="sm" class="flex-1 text-[11px]" @click.stop="$dispatch('open-approval-modal', { id: {{ $queue->id }} })">Setujui</flux:button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400 dark:text-zinc-500">
                            Kosong
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
    
    <livewire:queue.create-modal />
    <livewire:queue.consolidation-modal />
    <livewire:queue.approval-modal />
    <livewire:global.item-gallery-modal />
</div>

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
</style>
