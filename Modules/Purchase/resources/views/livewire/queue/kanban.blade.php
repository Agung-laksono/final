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
        'on_delivery' => ['title' => 'Dalam Perjalanan', 'color' => 'indigo'],
        'completed' => ['title' => 'Selesai / Ready', 'color' => 'emerald'],
    ]
]);

$queues = computed(function () {
    return PurchaseQueue::with('item')->latest()->get()->groupBy(function($q) {
        return $q->status ?? 'pending_approval';
    });
});

$updateStatus = function ($queueId, $newStatus) {
    if (!array_key_exists($newStatus, $this->columns)) return;
    
    $queue = PurchaseQueue::find($queueId);
    if ($queue) {
        $queue->status = $newStatus;
        $queue->save();
        $this->dispatch('status-updated');
    }
};

?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Kanban Permintaan Pembelian</flux:heading>
            <flux:subheading>Pantau alur dan status dari setiap antrean permintaan barang (Purchase Queue).</flux:subheading>
        </div>
        
        <flux:button variant="primary" icon="plus" href="{{ route('purchase.queues.kanban') }}">Buat Permintaan Baru</flux:button>
    </div>

    {{-- Kanban Board Area --}}
    <div class="flex gap-6 overflow-x-auto pb-4 h-[calc(100vh-12rem)]" x-data="kanbanBoard()">
        @foreach($columns as $statusKey => $column)
            <div class="flex-shrink-0 w-80 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col"
                 @dragover.prevent="dragOverColumn = '{{ $statusKey }}'"
                 @dragleave.prevent="dragOverColumn = null"
                 @drop.prevent="dropItem('{{ $statusKey }}')"
                 :class="{ 'ring-2 ring-blue-500/50 bg-blue-50/50 dark:bg-blue-900/20': dragOverColumn === '{{ $statusKey }}' }">
                
                {{-- Column Header --}}
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900 rounded-t-xl">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500 shadow-[0_0_8px_rgba(0,0,0,0.5)] shadow-{{ $column['color'] }}-500/50"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $column['title'] }}</h3>
                    </div>
                    <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800">{{ count($this->queues[$statusKey] ?? []) }}</flux:badge>
                </div>

                {{-- Column Items --}}
                <div class="flex-1 p-3 overflow-y-auto space-y-3 custom-scrollbar">
                    @forelse($this->queues[$statusKey] ?? [] as $queue)
                        <div draggable="true" 
                             @dragstart="dragStart($event, {{ $queue->id }})"
                             @dragend="dragEnd($event)"
                             class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 cursor-grab active:cursor-grabbing hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-colors group relative">
                            
                            {{-- Header Card --}}
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded-md">
                                    #PQ-{{ str_pad($queue->id, 4, '0', STR_PAD_LEFT) }}
                                </span>
                                <span class="text-[10px] text-zinc-400">{{ $queue->created_at->diffForHumans() }}</span>
                            </div>

                            {{-- Item Info --}}
                            <h4 class="font-medium text-zinc-900 dark:text-zinc-100 mb-1 leading-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                {{ $queue->item->name ?? 'Barang Dihapus' }}
                            </h4>
                            
                            {{-- Qty & Source --}}
                            <div class="flex items-center justify-between text-sm mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-zinc-500 uppercase tracking-wider">Jumlah</span>
                                    <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $queue->requested_qty }} <span class="text-xs font-normal">Unit</span></span>
                                </div>
                                <div class="flex flex-col text-right">
                                    <span class="text-[10px] text-zinc-500 uppercase tracking-wider">Sumber</span>
                                    <span class="font-medium text-zinc-600 dark:text-zinc-400">{{ ucwords(str_replace('_', ' ', $queue->source_type)) }}</span>
                                </div>
                            </div>
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
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('kanbanBoard', () => ({
            draggedItemId: null,
            dragOverColumn: null,

            dragStart(event, itemId) {
                this.draggedItemId = itemId;
                event.dataTransfer.effectAllowed = 'move';
                // Memberikan efek semi transparan pada item yang sedang ditarik
                setTimeout(() => event.target.classList.add('opacity-50', 'scale-95'), 0);
            },

            dragEnd(event) {
                this.draggedItemId = null;
                this.dragOverColumn = null;
                event.target.classList.remove('opacity-50', 'scale-95');
            },

            dropItem(statusKey) {
                if (this.draggedItemId) {
                    @this.call('updateStatus', this.draggedItemId, statusKey);
                }
                this.dragOverColumn = null;
            }
        }));
    });
</script>

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
