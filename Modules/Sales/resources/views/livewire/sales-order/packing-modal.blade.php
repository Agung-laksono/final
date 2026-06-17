<?php
use function Livewire\Volt\{state, on, usesFileUploads};
use Modules\Sales\Models\SalesOrder;
use Modules\Purchase\Models\Vendor;
use Illuminate\Support\Facades\Storage;

usesFileUploads();

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    
    'packing_fee' => 0,
    'packing_vendor' => null,
    'packing_receipt_file' => null,
    
    'selecting_for' => null,
    'items' => [],
    'fee_type' => 'borongan', // 'borongan' or 'per_item'
    'is_packed' => false,
]);

on(['open-packing-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = SalesOrder::with('items.item')->find($orderId);
    if ($this->order) {
        $this->packing_fee = $this->order->actual_packing_fee ?? 0;
        $this->packing_vendor = $this->order->packing_vendor_id ? Vendor::find($this->order->packing_vendor_id)?->toArray() : null;
        
        $this->items = $this->order->items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->item->name,
                'qty' => $item->qty,
                'actual_packing_fee' => $item->actual_packing_fee ?? 0,
            ];
        })->toArray();
        
        $this->is_packed = $this->order->is_packed;
        $this->show = true;
    }
}]);

on(['vendor-selected' => function ($vendorId = null) {
    if (!$this->show || $this->selecting_for !== 'packing') return;
    
    if ($vendorId) {
        $vendor = Vendor::find($vendorId);
        if ($vendor) {
            $this->packing_vendor = $vendor->toArray();
        }
    }
    $this->selecting_for = null;
}]);

$setSelectingFor = function ($type) {
    $this->selecting_for = $type;
};

$clearPackingVendor = function () {
    $this->packing_vendor = null;
};

$savePackingInfo = function () {
    abort_unless(auth()->user()->can('sales.order.update'), 403);

    if (!$this->order) return;

    $this->order->actual_packing_fee = $this->packing_fee;
    $this->order->packing_vendor_id = $this->packing_vendor['id'] ?? null;
    $this->order->is_packed = $this->is_packed;
    
    if ($this->packing_receipt_file) {
        if (is_string($this->packing_receipt_file) && str_starts_with($this->packing_receipt_file, 'data:image')) {
            list($type, $data) = explode(';', $this->packing_receipt_file);
            list(, $data)      = explode(',', $data);
            $data = base64_decode($data);
            
            $filename = 'receipts/packing_' . uniqid() . '.webp';
            Storage::disk('public')->put($filename, $data);
            $this->order->packing_receipt_path = $filename;
        } else {
            $path = $this->packing_receipt_file->store('receipts', 'public');
            $this->order->packing_receipt_path = $path;
        }
    }

    $this->order->save();

    // Simpan biaya aktual per item
    foreach ($this->items as $itemData) {
        \Modules\Sales\Models\SalesOrderItem::where('id', $itemData['id'])
            ->update(['actual_packing_fee' => $itemData['actual_packing_fee'] ?? 0]);
    }
    
    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('sales_order');
    $this->show = false;
    \Flux::toast('Detail Packing (Aktual) berhasil disimpan!', variant: 'success');
};

?>

<flux:modal wire:model="show" class="w-full md:w-[45rem] md:max-w-3xl">
    @if($order)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center">
                <flux:icon.archive-box class="w-5 h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Detail Packing</flux:heading>
                <flux:subheading class="mt-1 text-sm">
                    SO <strong>{{ $order->so_number }}</strong> - Masukkan biaya packing dan vendor (opsional).
                </flux:subheading>
            </div>
        </div>

        <div class="mt-6 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
            
            {{-- Toggle Tipe Biaya --}}
            <div class="mb-6 flex gap-6 border-b border-zinc-200 dark:border-zinc-700 pb-4">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="radio" wire:model.live="fee_type" value="borongan" class="text-purple-600 focus:ring-purple-500 w-4 h-4">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-purple-600 transition-colors">Borongan (Global)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="radio" wire:model.live="fee_type" value="per_item" class="text-purple-600 focus:ring-purple-500 w-4 h-4">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-purple-600 transition-colors">Per Item</span>
                </label>
            </div>

            {{-- Daftar Barang --}}
            <div x-show="$wire.fee_type === 'per_item'" x-cloak class="mb-6 space-y-3" x-transition>
                <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider mb-3">Biaya Packing Per Item (Aktual)</label>
                @foreach($items as $index => $item)
                    <div class="flex items-center justify-between p-3 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="flex-1">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $item['name'] }}</div>
                            <div class="text-xs text-zinc-500">Kuantitas: {{ $item['qty'] }}</div>
                        </div>
                        <div class="w-32 sm:w-48 shrink-0">
                            <x-rupiah-input wire:model="items.{{ $index }}.actual_packing_fee" placeholder="0" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div x-show="$wire.fee_type === 'per_item'" x-cloak>
                <flux:separator class="my-6" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Kolom Kiri: Input Biaya Global & Vendor --}}
                <div class="space-y-4">
                    <div x-show="$wire.fee_type === 'borongan'" x-cloak x-transition>
                        <label class="block mb-2 text-sm font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">Biaya Packing Tambahan (Borongan / Global)</label>
                        <x-rupiah-input wire:model="packing_fee" placeholder="0" />
                        <span class="text-[10px] text-zinc-500 mt-1 block">Diisi jika bayar packing untuk keseluruhan order.</span>
                    </div>
                    
                    <div class="pt-2">
                        <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Vendor Packing</label>
                        
                        @if($packing_vendor)
                            <div class="group flex items-center gap-3 p-3 rounded-xl border border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-900/20 transition-all">
                                <flux:avatar src="{{ $packing_vendor['image'] ? Storage::url($packing_vendor['image']) : '' }}" fallback="{{ substr($packing_vendor['name'], 0, 2) }}" class="shadow-sm w-10 h-10" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm leading-none truncate">{{ $packing_vendor['name'] }}</h3>
                                    </div>
                                    <div class="mt-1 flex flex-col gap-y-1 text-[10px] text-zinc-600 dark:text-zinc-400">
                                        <span class="truncate">{{ $packing_vendor['phone'] ?: 'Tidak ada nomor telepon' }}</span>
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    <flux:button variant="subtle" size="sm" icon="x-mark" class="text-zinc-400 hover:text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-full w-8 h-8 flex items-center justify-center p-0" wire:click="clearPackingVendor" title="Hapus Vendor"></flux:button>
                                </div>
                            </div>
                        @else
                            <flux:button variant="subtle" class="w-full justify-center border-dashed border-2 border-zinc-300 dark:border-zinc-600 text-zinc-500 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="setSelectingFor('packing'); $dispatchTo('global.vendor-gallery-modal', 'set-filter-type', { type: 'Packing', locked: true })" x-on:click="$flux.modal('vendor-gallery-modal').show()" icon="plus">
                                Pilih Vendor Packing
                            </flux:button>
                        @endif
                    </div>
                    
                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700 mt-4">
                        <flux:checkbox wire:model="is_packed" label="Tandai Packing Selesai" description="Jika dicentang, pesanan ini dianggap siap dikirim dan tombol Ekspedisi akan muncul." />
                    </div>
                </div>

                {{-- Kolom Kanan: Cropper Bukti Nota --}}
                <div class="pt-4 md:pt-0 md:pl-6 md:border-l border-zinc-200 dark:border-zinc-700">
                    <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Unggah Nota Packing (Opsional)</label>
                    <div class="bg-white dark:bg-zinc-900 p-2 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <x-image-cropper id="packing-cropper" wire:model="packing_receipt_file" :image="$packing_receipt_file && is_string($packing_receipt_file) && !str_starts_with($packing_receipt_file, 'data:image') ? Storage::url($packing_receipt_file) : null" accept="image/*" />
                    </div>
                    <div wire:loading wire:target="packing_receipt_file" class="text-xs text-purple-600 mt-2">Memproses gambar...</div>
                    @error('packing_receipt_file') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
            <flux:button variant="primary" wire:click="savePackingInfo" icon="check">Simpan Detail Packing</flux:button>
        </div>
    </div>
    @endif
</flux:modal>
