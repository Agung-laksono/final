<?php
use function Livewire\Volt\{state, on};
use Modules\Production\Models\ProductionOrder;

state([
    'show' => false,
    'order' => null,
    'stockMovements' => [],
    'customOrderItem' => null,
]);

on(['open-prod-detail-modal' => function ($orderId) {
    if (is_array($orderId)) {
        $orderId = $orderId['orderId'] ?? null;
    }
    
    $this->order = ProductionOrder::with(['item', 'creator', 'histories.vendor', 'purchaseOrder.vendor'])->find($orderId);
    
    if ($this->order) {
        // Find materials consumed for this PROD
        $this->stockMovements = \DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->where('stock_movements.reference_number', $this->order->order_number)
            ->where('stock_movements.type', 'out')
            ->select('stock_movements.*', 'items.name as item_name', 'items.code as item_sku')
            ->get();
            
        // Check if there are custom attributes from Sales Order
        if ($this->order->reference_number && str_starts_with($this->order->reference_number, 'ODM-')) {
            $so = \Modules\Sales\Models\SalesOrder::where('so_number', $this->order->reference_number)->first();
            if ($so) {
                $this->customOrderItem = \Modules\Sales\Models\SalesOrderItem::where('sales_order_id', $so->id)
                    ->where('item_id', $this->order->item_id)
                    ->where(function($q) {
                        $q->whereNotNull('custom_attributes')->orWhereNotNull('custom_attachments');
                    })
                    ->first();
            }
        }
    }
    
    $this->show = true;
}]);

?>

<div x-data="{ lightboxOpen: false, lightboxImg: '' }">
<flux:modal wire:model="show" class="md:w-[700px] max-w-full">
    @if($order)
        <div class="space-y-6">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="lg">Detail Pekerjaan: {{ $order->order_number }}</flux:heading>
                    <flux:subheading>Barang: <strong class="text-zinc-900 dark:text-white">{{ $order->item->name }}</strong></flux:subheading>
                </div>
                <flux:badge color="blue">
                    {{ str_replace('_', ' ', strtoupper($order->status)) }}
                </flux:badge>
            </div>
            
            @if($customOrderItem)
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 rounded-xl p-4 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-10">
                        <flux:icon.star class="w-24 h-24 text-amber-500" />
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                                <flux:icon.sparkles class="w-4 h-4" />
                            </div>
                            <flux:heading size="md" class="!text-amber-800 dark:!text-amber-400">Permintaan Produksi Custom</flux:heading>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if(!empty($customOrderItem->custom_attributes))
                                <div>
                                    <h4 class="text-xs font-bold text-amber-700 dark:text-amber-500 uppercase tracking-wider mb-2">Spesifikasi Khusus</h4>
                                    <div class="bg-white/60 dark:bg-zinc-900/40 rounded-lg p-3 space-y-2 border border-amber-100 dark:border-amber-800/30">
                                        @foreach($customOrderItem->custom_attributes as $attr)
                                            <div class="flex justify-between items-start text-sm">
                                                <span class="text-zinc-500 font-medium">{{ $attr['key'] ?? '-' }}</span>
                                                <span class="text-zinc-900 dark:text-zinc-100 font-bold text-right">{{ $attr['value'] ?? '-' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            
                            @if(!empty($customOrderItem->custom_attachments))
                                <div>
                                    <h4 class="text-xs font-bold text-amber-700 dark:text-amber-500 uppercase tracking-wider mb-2">Lampiran / Sketsa</h4>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                        @foreach($customOrderItem->custom_attachments as $idx => $path)
                                            <div @click="lightboxImg = '{{ asset('storage/' . $path) }}'; lightboxOpen = true" class="cursor-pointer block relative group rounded-lg overflow-hidden border border-amber-200 dark:border-amber-700/50 aspect-square">
                                                <img src="{{ asset('storage/' . $path) }}" class="w-full h-full object-cover transition-transform group-hover:scale-110" />
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                    <flux:icon.arrows-pointing-out class="w-5 h-5 text-white" />
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                        
                        @if($customOrderItem->notes)
                            <div class="mt-4 pt-3 border-t border-amber-200/60 dark:border-amber-800/60">
                                <h4 class="text-xs font-bold text-amber-700 dark:text-amber-500 uppercase tracking-wider mb-1">Catatan Tambahan Sales</h4>
                                <div class="text-sm text-zinc-700 dark:text-zinc-300 italic prose prose-sm prose-p:my-0">{!! $customOrderItem->notes !!}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm bg-zinc-50 dark:bg-zinc-800/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <div>
                        <span class="text-zinc-500">Target Qty:</span><br>
                        <strong class="text-zinc-900 dark:text-zinc-100">{{ $order->requested_qty }}</strong>
                    </div>
                    <div>
                        <span class="text-zinc-500">Pembuat:</span><br>
                        <strong class="text-zinc-900 dark:text-zinc-100">{{ $order->creator->name ?? 'Sistem' }}</strong>
                    </div>
                    <div>
                        <span class="text-zinc-500">Referensi:</span><br>
                        <strong class="text-zinc-900 dark:text-zinc-100">{{ $order->reference_number ?: '-' }}</strong>
                    </div>
                    <div>
                        <span class="text-zinc-500">Tgl Dibuat:</span><br>
                        <strong class="text-zinc-900 dark:text-zinc-100">{{ \Carbon\Carbon::parse($order->created_at)->format('d M Y H:i') }}</strong>
                    </div>
                </div>

                @if($order->status === 'in_production' && $order->purchaseOrder)
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-xl border border-blue-200 dark:border-blue-800">
                        <flux:heading size="sm" class="!text-blue-700 dark:!text-blue-300 mb-2">Posisi Saat Ini (Aktif)</flux:heading>
                        <div class="text-sm">
                            Fase: <strong>{{ ucfirst($order->phase_type) }}</strong><br>
                            Vendor: <strong>{{ $order->purchaseOrder->vendor->name ?? '-' }}</strong><br>
                            PO: <a href="#" wire:click.prevent="$dispatch('open-po-detail-modal', { poId: {{ $order->purchase_order_id }} })" class="text-blue-600 hover:underline">{{ $order->purchaseOrder->po_number }}</a>
                        </div>
                    </div>
                @endif
                
                @if(count($stockMovements) > 0)
                    <div>
                        <flux:heading size="sm" class="mb-3">Bahan Fisik Terpakai (BOM Consumed)</flux:heading>
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                            <table class="w-full text-sm text-left table-mobile-cards">
                                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">Material</th>
                                        <th class="px-3 py-2 font-medium">SKU</th>
                                        <th class="px-3 py-2 font-medium text-right">Qty Terpotong</th>
                                        <th class="px-3 py-2 font-medium">Tanggal Keluar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach($stockMovements as $movement)
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $movement->item_name }}</td>
                                            <td class="px-3 py-2 text-zinc-500">{{ $movement->item_sku }}</td>
                                            <td class="px-3 py-2 text-right text-red-600 font-medium">-{{ $movement->quantity }}</td>
                                            <td class="px-3 py-2 text-zinc-500">{{ \Carbon\Carbon::parse($movement->created_at)->format('d M Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div>
                    <flux:heading size="sm" class="mb-3">Jejak Riwayat Perjalanan (Audit Trail)</flux:heading>
                    
                    @if($order->histories && $order->histories->count() > 0)
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                            <table class="w-full text-sm text-left table-mobile-cards">
                                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">Tanggal</th>
                                        <th class="px-3 py-2 font-medium">Fase Selesai</th>
                                        <th class="px-3 py-2 font-medium">Vendor / Eksekutor</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach($order->histories()->orderBy('created_at', 'asc')->get() as $history)
                                        <tr>
                                            <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">
                                                {{ \Carbon\Carbon::parse($history->created_at)->format('d M Y H:i') }}
                                            </td>
                                            <td class="px-3 py-2 font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ ucfirst($history->phase) }}
                                            </td>
                                            <td class="px-3 py-2">
                                                {{ $history->vendor->name ?? 'Internal / Unknown' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 text-sm p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 text-center">
                            Belum ada riwayat fase yang diselesaikan.
                        </div>
                    @endif
                    
                    @if($order->notes)
                        <div class="mt-4 text-xs text-zinc-500">
                            <strong>Catatan Sistem:</strong><br>
                            <div class="whitespace-pre-wrap mt-1 prose prose-sm max-w-none">{!! $order->notes !!}</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex justify-between items-center pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <div>
                    @if(in_array($order->status, ['material_fulfillment', 'waiting_material', 'waiting_vendor', 'pending_approval']) && $order->requested_qty > 1)
                        <flux:button variant="subtle" icon="arrows-right-left" wire:click.stop="$dispatch('open-split-order-modal', { orderId: {{ $order->id }} })" class="text-amber-600 hover:text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-900/50">
                            Pecah SPK
                        </flux:button>
                    @endif
                </div>
                <flux:button variant="ghost" wire:click="$set('show', false)"> Tutup </flux:button>
            </div>
        </div>
    @endif
</flux:modal>

    <!-- Image Lightbox -->
    <template x-teleport="body">
        <div x-show="lightboxOpen" x-transition.opacity class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/90 p-4 backdrop-blur-sm" style="display: none;">
            <button @click="lightboxOpen = false" class="absolute top-6 right-6 text-white hover:text-amber-400 bg-black/50 hover:bg-black/80 rounded-full p-2 transition-colors">
                <flux:icon.x-mark class="w-8 h-8" />
            </button>
            <img :src="lightboxImg" class="max-w-[95vw] max-h-[95vh] object-contain rounded-lg shadow-2xl" @click.away="lightboxOpen = false" />
        </div>
    </template>
</div>
