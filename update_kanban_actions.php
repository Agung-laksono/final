<?php
$dest = __DIR__ . '/Modules/Sales/resources/views/livewire/sales-order/kanban.blade.php';
$content = file_get_contents($dest);

$oldFooter = <<<'EOD'
                            {{-- Footer Kartu (Pembuat & Aksi) --}}
                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                <div class="flex items-center gap-1.5" title="Dibuat oleh {{ $order->creator->name ?? 'Sistem' }}">
                                    <flux:icon.user-circle class="w-4 h-4 text-zinc-400" />
                                    <span class="text-[10px] font-medium text-zinc-500 truncate max-w-[100px]">
                                        {{ explode(' ', $order->creator->name ?? 'Sistem')[0] }}
                                    </span>
                                </div>
                                
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <flux:button size="sm" variant="subtle" icon="eye" class="h-6 w-6 p-0" title="Detail SO" />
                                </div>
                            </div>
EOD;

$newFooter = <<<'EOD'
                            {{-- Footer Kartu (Pembuat & Aksi) --}}
                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                <div class="flex items-center gap-1.5" title="Dibuat oleh {{ $order->creator->name ?? 'Sistem' }}">
                                    <flux:icon.user-circle class="w-4 h-4 text-zinc-400" />
                                    <span class="text-[10px] font-medium text-zinc-500 truncate max-w-[100px]">
                                        {{ explode(' ', $order->creator->name ?? 'Sistem')[0] }}
                                    </span>
                                </div>
                                
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <flux:button size="sm" variant="subtle" icon="eye" class="h-6 w-6 p-0" title="Detail SO" wire:click.stop="$dispatch('open-detail-modal', { orderId: {{ $order->id }} })" />
                                    
                                    {{-- Tombol Pembayaran --}}
                                    <flux:button size="sm" variant="subtle" icon="banknotes" class="h-6 w-6 p-0 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/50" title="Input Pembayaran" wire:click.stop="$dispatch('open-payment-modal', { orderId: {{ $order->id }} })" />
                                    
                                    {{-- Tombol Aksi Spesifik --}}
                                    @if($statusKey === 'pending_approval')
                                        @can('sales.order.update')
                                            <flux:button size="sm" variant="subtle" icon="check-circle" class="h-6 w-6 p-0 text-amber-600 hover:text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-900/50" title="Persetujuan" wire:click.stop="$dispatch('open-approval-modal', { orderId: {{ $order->id }} })" />
                                        @endcan
                                    @elseif($statusKey === 'processing')
                                        @can('sales.order.update')
                                            <flux:button size="sm" variant="subtle" icon="qr-code" class="h-6 w-6 p-0 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/50" title="Fulfillment Gudang" wire:click.stop="$dispatch('open-fulfillment-modal', { orderId: {{ $order->id }} })" />
                                        @endcan
                                    @elseif($statusKey === 'packing')
                                        @can('sales.order.update')
                                            <flux:button size="sm" variant="subtle" icon="archive-box" class="h-6 w-6 p-0 text-purple-600 hover:text-purple-700 hover:bg-purple-50 dark:hover:bg-purple-900/50" title="Input Ongkir & Kirim" wire:click.stop="$dispatch('open-packing-modal', { orderId: {{ $order->id }} })" />
                                        @endcan
                                    @elseif($statusKey === 'shipping')
                                        @can('sales.order.update')
                                            <flux:button size="sm" variant="subtle" icon="truck" class="h-6 w-6 p-0 text-orange-600 hover:text-orange-700 hover:bg-orange-50 dark:hover:bg-orange-900/50" title="Tandai Sampai" wire:click.stop="markAsArrived({{ $order->id }})" />
                                        @endcan
                                    @endif
                                </div>
                            </div>
EOD;

$content = str_replace($oldFooter, $newFooter, $content);

// Tambahkan logic markAsArrived di atas
$oldLogic = <<<'EOD'
on(['status-updated' => function () {
    // Kosong saja, tujuannya hanya memancing re-render agar computed $orders dijalankan ulang
}]);
EOD;

$newLogic = <<<'EOD'
$markAsArrived = function ($orderId) {
    abort_unless(auth()->user()->can('sales.order.update'), 403);
    
    $so = SalesOrder::find($orderId);
    if ($so) {
        $so->status = 'completed';
        $so->save();
        
        // Di sini bisa ditambahkan logika pengurangan stok otomatis (mutasi keluar)
        // secara formal di inventory system.
        
        \Flux::toast('Pesanan selesai! Barang sudah diterima pelanggan.', variant: 'success');
        $this->dispatch('status-updated');
    }
};

on(['status-updated' => function () {
    // Kosong saja, tujuannya hanya memancing re-render agar computed $orders dijalankan ulang
}]);
EOD;

$content = str_replace($oldLogic, $newLogic, $content);

file_put_contents($dest, $content);
echo "Footer actions updated!";
