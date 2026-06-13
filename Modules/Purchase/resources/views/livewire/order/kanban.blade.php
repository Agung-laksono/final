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
    abort_unless(auth()->user()->can('purchase.update'), 403, 'Anda tidak memiliki izin untuk mengubah status PO.');
    
    if (!array_key_exists($newStatus, $this->columns)) return;
    
    $po = PurchaseOrder::find($orderId);
    if ($po) {
        $po->status = $newStatus;
        $po->save();
        $this->dispatch('status-updated');
    }
};

on(['status-updated' => function () {
    // Kosong saja, tujuannya hanya memancing re-render agar computed $orders dijalankan ulang
}]);

?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Kanban Purchase Order (PO)</flux:heading>
            <flux:subheading>Atur dan pantau progres dokumen pemesanan ke Supplier/Vendor secara visual.</flux:subheading>
        </div>
        
        @can('purchase.create')
            <flux:button variant="primary" icon="plus" href="{{ route('purchase.orders.create') }}">Buat PO Baru</flux:button>
        @endcan
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
                             wire:click="$dispatch('open-detail-modal', { orderId: {{ $po->id }} })"
                             class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 cursor-grab active:cursor-grabbing hover:-translate-y-1 hover:shadow-md hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-all duration-300 group relative">
                            
                            {{-- Header Card --}}
                            <div class="flex justify-between items-start mb-3">
                                <span class="font-mono text-xs font-bold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 px-2 py-1 rounded-md">
                                    {{ $po->po_number }}
                                </span>
                                <div class="flex items-center text-[10px] text-zinc-400 font-medium">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    {{ \Carbon\Carbon::parse($po->order_date)->format('d M Y') }}
                                </div>
                            </div>

                            {{-- Vendor Info --}}
                            <div class="flex items-center gap-3 mb-4">
                                <div class="relative">
                                    <flux:avatar src="{{ $po->vendor?->image ? Storage::url($po->vendor->image) : '' }}" fallback="{{ substr($po->vendor?->name ?? '?', 0, 2) }}" size="sm" class="ring-2 ring-white dark:ring-zinc-900 shadow-sm" />
                                </div>
                                <div class="flex flex-col overflow-hidden">
                                    <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200 truncate">
                                        {{ $po->vendor?->name ?? 'Vendor Terhapus' }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400 truncate">Supplier</span>
                                </div>
                            </div>
                            
                            {{-- Total & Tax --}}
                            <div class="flex items-end justify-between pt-3 border-t border-dashed border-zinc-200 dark:border-zinc-700">
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-0.5">Total Nilai</span>
                                    <span class="text-lg font-black text-emerald-600 dark:text-emerald-400 tracking-tight leading-none">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center">
                                    @if($po->pajak)
                                        <flux:badge size="sm" color="amber" class="font-bold">Tax</flux:badge>
                                    @endif
                                </div>
                            </div>

                            {{-- Action Buttons --}}
                                @if(in_array($statusKey, ['processing', 'partially_received']))
                                    <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                        @can('purchase.update')
                                            <button wire:click.stop="$dispatch('open-receipt-modal', { orderId: {{ $po->id }} })" class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-400 hover:to-emerald-400 text-white text-sm font-semibold py-2 px-4 rounded-lg shadow-sm hover:shadow-md transition-all">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                                Terima Barang
                                            </button>
                                        @else
                                            <button disabled class="w-full flex items-center justify-center gap-2 bg-zinc-200 dark:bg-zinc-800 text-zinc-400 text-sm font-semibold py-2 px-4 rounded-lg cursor-not-allowed">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                                Terima Barang
                                            </button>
                                        @endcan
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

    <livewire:order.receipt-modal />
    <livewire:order.detail-modal />

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
                    // Prevent dropping to system-calculated columns
                    if (statusKey === 'partially_received' || statusKey === 'completed') {
                        Flux.toast({ title: 'Akses Ditolak', description: 'Gunakan tombol Terima Barang untuk memperbarui status penerimaan.', variant: 'warning' });
                        this.dragOverColumn = null;
                        return;
                    }

                    if (this.draggedItemId) {
                        @this.call('updateStatus', this.draggedItemId, statusKey);
                    }
                    this.dragOverColumn = null;
                }
            }));
        });
    </script>
    
    <livewire:print-label-modal />
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
