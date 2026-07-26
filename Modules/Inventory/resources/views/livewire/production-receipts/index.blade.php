<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Production\Models\ProductionOrder;

layout('layouts.app');
title('Penerimaan Hasil Produksi');

state([
    'search' => '',
]);

$receipts = computed(function () {
    $query = ProductionOrder::with(['item', 'item.type', 'purchaseOrder', 'purchaseOrder.vendor', 'targetWarehouse'])
        ->where('status', 'receiving')
        ->whereNotNull('target_warehouse_id')
        ->latest();
        
    if ($this->search) {
        $query->where('order_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('item', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    
    return $query->get();
});

on(['status-updated' => function () {}]);

?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Penerimaan Fisik (QC)</flux:heading>
            <flux:subheading>Validasi barang fisik berdasarkan alokasi dari Kepala Inventory.</flux:subheading>
        </div>
        <div class="w-full md:w-64">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari PO, Ref, atau Barang..." />
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left table-mobile-cards">
                <thead class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800 uppercase">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Tgl Selesai</th>
                        <th class="px-6 py-3 font-semibold">No. SPK</th>
                        <th class="px-6 py-3 font-semibold">Barang</th>
                        <th class="px-6 py-3 font-semibold text-center">Kuantitas</th>
                        <th class="px-6 py-3 font-semibold">Tujuan Gudang</th>
                        <th class="px-6 py-3 font-semibold">Vendor/Asal</th>
                        <th class="px-6 py-3 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->receipts as $req)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                                {{ $req->updated_at->format('d M Y, H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-zinc-900 dark:text-zinc-100">
                                {{ $req->order_number }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-zinc-900 dark:text-white">{{ $req->item->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $req->item->type->name ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="font-bold text-blue-600 dark:text-blue-400">{{ $req->requested_qty }}</span> <span class="text-xs text-zinc-500">{{ $req->item->unit->name ?? 'pcs' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="amber">{{ $req->targetWarehouse->name ?? 'Unknown' }}</flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                                @if($req->purchaseOrder)
                                    {{ $req->purchaseOrder->vendor->name ?? 'Vendor Maklon' }}
                                    <div class="text-[10px] text-zinc-400">PO: {{ $req->purchaseOrder->po_number }}</div>
                                @else
                                    <span class="text-indigo-600 dark:text-indigo-400 font-medium">Internal Produksi</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @php
                                    $user = auth()->user();
                                    $isAuthorized = $user->hasRole('Super Admin') || $user->warehouses->pluck('id')->contains($req->target_warehouse_id);
                                @endphp
                                @if($isAuthorized)
                                    <flux:button size="sm" variant="primary" wire:click="$dispatch('open-qc-modal', { orderId: {{ $req->id }} })">Lanjut QC & Terima</flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" disabled tooltip="Anda tidak ditugaskan di gudang ini">Tidak Ada Akses</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-zinc-500">
                                <flux:icon.inbox class="w-8 h-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                                Tidak ada barang yang menunggu penerimaan gudang.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <livewire:production-receipts.qc-modal />
</div>
