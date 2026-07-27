<?php
use function Livewire\Volt\{state, on, computed, usesPagination};
use Modules\Purchase\Models\PurchaseOrderItem;
use Modules\Inventory\Models\Item;

usesPagination();

state([
    'show' => false,
    'itemId' => null,
    'item' => null,
    'perPage' => 5,
]);

on(['open-price-history' => function ($itemId = null) {
    $this->reset(['itemId', 'item', 'perPage']);
    $this->resetPage();
    
    $this->itemId = $itemId;
    if ($this->itemId) {
        $this->item = Item::find($this->itemId);
    }
    
    $this->show = true;
}]);

$loadMore = function () {
    $this->perPage += 5;
};

$history = computed(function () {
    if (!$this->itemId) return collect();
    
    // Ambil histori harga dari PurchaseOrderItems (Karena Maklon disimpan di PO)
    return PurchaseOrderItem::with('purchaseOrder.vendor')
        ->where('item_id', $this->itemId)
        ->whereHas('purchaseOrder', function($q) {
            $q->whereNotNull('vendor_id');
        })
        ->latest('created_at')
        ->paginate($this->perPage);
});
?>

<flux:modal wire:model="show" class="md:w-[600px]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Riwayat Biaya Maklon/Vendor</flux:heading>
            <flux:subheading>Daftar harga borongan yang pernah dibayarkan ke vendor untuk barang ini.</flux:subheading>
        </div>

        @if($item)
            <div class="flex items-center gap-3 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="w-10 h-10 rounded bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                    <flux:icon.cube class="w-5 h-5" />
                </div>
                <div>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100">
                        @if($item->alias)
                            {{ $item->alias }} <span class="text-xs text-zinc-500 normal-case font-medium ml-1">- {{ $item->name }}</span>
                        @else
                            {{ $item->name }}
                        @endif
                    </div>
                    <div class="text-xs text-zinc-500">{{ $item->code }}</div>
                </div>
            </div>

            <div class="mt-4">
                @if($this->history->count() > 0)
                    <div class="space-y-3">
                        @foreach($this->history as $hist)
                            <div class="flex justify-between items-center p-3 border-b border-zinc-100 dark:border-zinc-800 last:border-0 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <div>
                                    <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $hist->purchaseOrder->vendor->name ?? 'Vendor Tidak Diketahui' }}
                                    </div>
                                    <div class="text-xs text-zinc-500 flex gap-2 mt-0.5">
                                        <span>{{ $hist->created_at->format('d M Y') }}</span>
                                        <span>•</span>
                                        <span>Ref: {{ $hist->purchaseOrder->po_number }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-emerald-600">Rp {{ number_format($hist->unit_price, 0, ',', '.') }}</div>
                                    <div class="text-xs text-zinc-500">per unit</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="mt-4">
                        <x-load-more :paginator="$this->history" item-name="riwayat" />
                    </div>
                @else
                    <div class="p-6 text-center bg-zinc-50 dark:bg-zinc-800/30 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
                        <flux:icon.clock class="w-8 h-8 text-zinc-300 mx-auto mb-2" />
                        <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Belum ada riwayat biaya</div>
                        <div class="text-xs text-zinc-500">Barang ini belum pernah di-maklon-kan ke vendor manapun.</div>
                    </div>
                @endif
            </div>
        @endif

        <div class="flex justify-end pt-4">
            <flux:button variant="ghost" wire:click="$set('show', false)"> Tutup </flux:button>
        </div>
    </div>
</flux:modal>
