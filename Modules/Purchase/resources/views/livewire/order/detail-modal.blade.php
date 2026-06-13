<?php

use function Livewire\Volt\{state, on};
use Modules\Purchase\Models\PurchaseOrder;

state([
    'show' => false,
    'order' => null,
]);

on(['open-detail-modal' => function ($orderId) {
    $this->order = PurchaseOrder::with(['vendor', 'items.item'])->findOrFail($orderId);
    $this->show = true;
}]);

$getStatusBadge = function ($status) {
    $map = [
        'draft' => ['label' => 'Draft', 'color' => 'zinc'],
        'pending_approval' => ['label' => 'Menunggu ACC', 'color' => 'amber'],
        'processing' => ['label' => 'Diproses Vendor', 'color' => 'blue'],
        'partially_received' => ['label' => 'Diterima Sebagian', 'color' => 'indigo'],
        'completed' => ['label' => 'Selesai', 'color' => 'emerald'],
    ];
    
    $mapped = $map[$status] ?? ['label' => $status, 'color' => 'zinc'];
    
    return "<span class=\"inline-flex items-center rounded-md bg-{$mapped['color']}-50 px-2 py-1 text-xs font-medium text-{$mapped['color']}-700 ring-1 ring-inset ring-{$mapped['color']}-600/20\">{$mapped['label']}</span>";
};

?>

<div>
    <flux:modal wire:model="show" class="w-full md:w-[800px] lg:w-[1000px] space-y-6">
        @if($order)
            <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-4">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <flux:heading size="xl">{{ $order->po_number }}</flux:heading>
                        {!! $this->getStatusBadge($order->status) !!}
                    </div>
                    <flux:subheading class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Tgl Order: {{ \Carbon\Carbon::parse($order->order_date)->translatedFormat('d F Y') }}
                    </flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Info Vendor -->
                <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-3">Informasi Vendor</div>
                    <div class="flex items-center gap-3">
                        <flux:avatar src="{{ $order->vendor?->image ? Storage::url($order->vendor->image) : '' }}" fallback="{{ substr($order->vendor?->name ?? '?', 0, 2) }}" size="md" />
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->vendor?->name ?? 'Vendor Terhapus' }}</div>
                            <div class="text-sm text-zinc-500">{{ $order->vendor?->phone ?? '-' }}</div>
                        </div>
                    </div>
                    @if($order->vendor?->address)
                        <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2">
                            <svg class="w-4 h-4 inline-block mr-1 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            {{ $order->vendor->address }}
                        </div>
                    @endif
                </div>

                <!-- Info PO -->
                <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 flex flex-col justify-center">
                    <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-1">Catatan Pesanan</div>
                    <div class="text-sm text-zinc-700 dark:text-zinc-300 italic">
                        {{ $order->notes ?: 'Tidak ada catatan.' }}
                    </div>
                </div>
            </div>

            <!-- Rincian Barang -->
            <div>
                <div class="text-sm font-bold text-zinc-800 dark:text-zinc-200 mb-3 border-b border-zinc-200 dark:border-zinc-700 pb-2">Rincian Pesanan</div>
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <th class="px-4 py-2 font-medium">Barang</th>
                                <th class="px-4 py-2 font-medium text-right">Qty</th>
                                <th class="px-4 py-2 font-medium text-right">Diterima</th>
                                <th class="px-4 py-2 font-medium text-right">Harga Satuan</th>
                                <th class="px-4 py-2 font-medium text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($order->items as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->item->name }}</div>
                                        <div class="text-xs font-mono text-zinc-500">{{ $item->item->code }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if($item->received_quantity >= $item->quantity)
                                            <span class="text-emerald-600 font-medium">{{ number_format($item->received_quantity, 0, ',', '.') }}</span>
                                        @elseif($item->received_quantity > 0)
                                            <span class="text-amber-600 font-medium">{{ number_format($item->received_quantity, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-zinc-400">0</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-medium">Rp {{ number_format($item->quantity * $item->unit_price, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right font-bold text-zinc-700 dark:text-zinc-300">Total Keseluruhan</td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400 text-lg">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                @if($order->pajak)
                <div class="mt-2 text-xs text-right text-amber-600 italic">
                    * Total nilai sudah termasuk PPN.
                </div>
                @endif
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <flux:button wire:click="$set('show', false)">Tutup</flux:button>
                @if(in_array($order->status, ['processing', 'partially_received']))
                    <flux:button variant="primary" wire:click="$dispatch('open-receipt-modal', { orderId: {{ $order->id }} }); $set('show', false)">
                        📦 Terima Barang
                    </flux:button>
                @endif
            </div>
        @else
            <div class="p-8 text-center text-zinc-500">Memuat data...</div>
        @endif
    </flux:modal>
</div>
