<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Purchase\Models\PurchaseOrder;

layout('layouts.app');
title('Kanban Purchase Order (PO)');

// Definisi Kolom Kanban untuk PO
state([
    'columns' => [
        'draft' => ['title' => 'Draft', 'color' => 'zinc'],
        'pending_approval' => ['title' => 'Menunggu ACC', 'color' => 'amber'],
        'processing' => ['title' => 'Diproses Vendor', 'color' => 'blue'],
        'partially_received' => ['title' => 'Diterima Sebagian', 'color' => 'indigo'],
        'completed' => ['title' => 'Selesai', 'color' => 'emerald'],
    ]
]);

$orders = computed(function () {
    return PurchaseOrder::with('vendor')->latest()->get()->groupBy(function($po) {
        return $po->status ?? 'draft';
    });
});

$updateStatus = function ($orderId, $newStatus) {
    if (!array_key_exists($newStatus, $this->columns)) return;
    
    $po = PurchaseOrder::find($orderId);
    if ($po) {
        $po->status = $newStatus;
        $po->save();
        $this->dispatch('status-updated');
    }
};

?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Kanban Purchase Order (PO)</flux:heading>
            <flux:subheading>Atur dan pantau progres dokumen pemesanan ke Supplier/Vendor secara visual.</flux:subheading>
        </div>
        
        <flux:button variant="primary" icon="plus" href="{{ route('purchase.orders.create') }}" wire:navigate>Buat PO Baru</flux:button>
    </div>

    {{-- Kanban Board Area --}}
    <div class="flex gap-6 overflow-x-auto pb-4 h-[calc(100vh-12rem)]" x-data="kanbanBoardOrder()">
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
                    <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800">{{ count($this->orders[$statusKey] ?? []) }}</flux:badge>
                </div>

                {{-- Column Items --}}
                <div class="flex-1 p-3 overflow-y-auto space-y-3 custom-scrollbar">
                    @forelse($this->orders[$statusKey] ?? [] as $po)
                        <div draggable="true" 
                             @dragstart="dragStart($event, {{ $po->id }})"
                             @dragend="dragEnd($event)"
                             class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 cursor-grab active:cursor-grabbing hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-colors group relative">
                            
                            {{-- Header Card --}}
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded-md">
                                    {{ $po->po_number }}
                                </span>
                                <span class="text-[10px] text-zinc-400">{{ \Carbon\Carbon::parse($po->order_date)->format('d M Y') }}</span>
                            </div>

                            {{-- Vendor Info --}}
                            <div class="flex items-center gap-2 mb-3">
                                <flux:avatar src="{{ $po->vendor?->image ? Storage::url($po->vendor->image) : '' }}" fallback="{{ substr($po->vendor?->name ?? '?', 0, 2) }}" size="sm" />
                                <span class="font-medium text-sm text-zinc-700 dark:text-zinc-300 truncate">
                                    {{ $po->vendor?->name ?? 'Vendor Terhapus' }}
                                </span>
                            </div>
                            
                            {{-- Total & Tax --}}
                            <div class="flex items-center justify-between text-sm pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-zinc-500 uppercase tracking-wider">Total Nilai</span>
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    @if($po->pajak)
                                        <flux:badge size="sm" color="amber">Tax</flux:badge>
                                    @endif
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
        Alpine.data('kanbanBoardOrder', () => ({
            draggedItemId: null,
            dragOverColumn: null,

            dragStart(event, itemId) {
                this.draggedItemId = itemId;
                event.dataTransfer.effectAllowed = 'move';
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
