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
    
    'shipping_fee' => 0,
    'courier_vendor' => null,
    'shipping_receipt_file' => null,
    
    'selecting_for' => null,
    'items' => [],
    'fee_type' => 'borongan', // 'borongan' or 'per_item'
    'mark_as_shipped' => true,
]);

on(['open-shipping-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = SalesOrder::with('items.item')->find($orderId);
    if ($this->order) {
        $this->shipping_fee = $this->order->actual_shipping_fee ?? 0;
        $this->courier_vendor = $this->order->courier_vendor_id ? Vendor::find($this->order->courier_vendor_id)?->toArray() : null;
        
        $this->items = $this->order->items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->item->name,
                'qty' => $item->qty,
                'actual_shipping_fee' => $item->actual_shipping_fee ?? 0,
            ];
        })->toArray();
        
        $this->show = true;
    }
}]);

$setCourierVendor = function ($data = null) {
    if (!$this->show || $this->selecting_for !== 'courier') return;
    
    if ($data) {
        // Alpine $dispatch('vendor-selected', { vendor: {...} }) will pass an array
        $vendorData = null;
        if (is_array($data)) {
            if (isset($data['vendor'])) {
                $vendorData = $data['vendor'];
            } elseif (isset($data['id'])) {
                $vendorData = $data;
            }
        } elseif (is_numeric($data)) {
            $v = \Modules\Purchase\Models\Vendor::find($data);
            if ($v) $vendorData = $v->toArray();
        }
        
        if ($vendorData) {
            $this->courier_vendor = $vendorData;
        }
    }
    $this->selecting_for = null;
};

$setSelectingFor = function ($type) {
    $this->selecting_for = $type;
};

$clearCourierVendor = function () {
    $this->courier_vendor = null;
};

$saveShippingInfo = function () {
    abort_unless(auth()->user()->can('sales.order.update'), 403);
    
    $this->validate([
        'courier_vendor' => 'required',
    ], [
        'courier_vendor.required' => 'Vendor Ekspedisi wajib dipilih sebelum mengirim pesanan.',
    ]);

    if (!$this->order) return;

    $this->order->actual_shipping_fee = $this->shipping_fee;
    $this->order->courier_vendor_id = $this->courier_vendor['id'] ?? null;
    $this->order->courier_vendor = $this->courier_vendor['name'] ?? '';
    
    if ($this->shipping_receipt_file) {
        if (is_string($this->shipping_receipt_file) && str_starts_with($this->shipping_receipt_file, 'data:image')) {
            list($type, $data) = explode(';', $this->shipping_receipt_file);
            list(, $data)      = explode(',', $data);
            $data = base64_decode($data);
            
            $filename = 'receipts/shipping_' . uniqid() . '.webp';
            Storage::disk('public')->put($filename, $data);
            $this->order->shipping_receipt_path = $filename;
        } else {
            $path = $this->shipping_receipt_file->store('receipts', 'public');
            $this->order->shipping_receipt_path = $path;
        }
    }
    
    if ($this->mark_as_shipped) {
        $this->order->status = 'shipping';
        
        // Update status barcode dari booked menjadi sold
        $labelIds = \Modules\Sales\Models\SalesOrderFulfillment::where('sales_order_id', $this->order->id)
            ->whereNotNull('item_label_id')
            ->pluck('item_label_id');
            
        if ($labelIds->isNotEmpty()) {
            $labels = \Modules\Inventory\Models\ItemLabel::whereIn('id', $labelIds)->where('status', 'booked')->get();
            foreach($labels as $lbl) {
                $lbl->status = 'sold';
                $lbl->notes = $lbl->notes . "\n[Shipping]: Telah diserahkan ke Ekspedisi.";
                $lbl->save();
            }
        }
    }
    
    $this->order->save();
    
    // Simpan biaya aktual per item
    foreach ($this->items as $itemData) {
        \Modules\Sales\Models\SalesOrderItem::where('id', $itemData['id'])
            ->update(['actual_shipping_fee' => $itemData['actual_shipping_fee'] ?? 0]);
    }
    
    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('sales_order');
    $this->show = false;
    \Flux::toast('Biaya Ekspedisi (Aktual) tersimpan! Pesanan kini dalam status Pengiriman.', variant: 'success');
};

?>

<flux:modal wire:model="show" class="w-full md:w-[45rem] md:max-w-3xl" @vendor-selected.window="$wire.setCourierVendor($event.detail)">
    @if($order)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center">
                <flux:icon.truck class="w-5 h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Kirim Pesanan (Ekspedisi)</flux:heading>
                <flux:subheading class="mt-1 text-sm">
                    SO <strong>{{ $order->so_number }}</strong> - Masukkan detail ongkir dan vendor pengiriman.
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
                <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider mb-3">Ongkir Per Item (Aktual)</label>
                @foreach($items as $index => $item)
                    <div class="flex items-center justify-between p-3 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="flex-1">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $item['name'] }}</div>
                            <div class="text-xs text-zinc-500">Kuantitas: {{ $item['qty'] }}</div>
                        </div>
                        <div class="w-32 sm:w-48 shrink-0">
                            <x-rupiah-input wire:model="items.{{ $index }}.actual_shipping_fee" placeholder="0" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div x-show="$wire.fee_type === 'per_item'" x-cloak>
                <flux:separator class="my-6" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Kolom Kiri: Input Ongkir Global & Ekspedisi --}}
                <div class="space-y-4">
                    <div x-show="$wire.fee_type === 'borongan'" x-cloak x-transition>
                        <label class="block mb-2 text-sm font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">Ongkir Tambahan (Borongan / Global)</label>
                        <x-rupiah-input wire:model="shipping_fee" placeholder="0" />
                        <span class="text-[10px] text-zinc-500 mt-1 block">Diisi jika bayar borongan (sewa 1 pick up) untuk keseluruhan order.</span>
                        @error('shipping_fee') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="pt-2">
                        <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Pilih Ekspedisi / Kurir <span class="text-red-500">*</span></label>
                        
                        @if($courier_vendor)
                            <div class="group flex items-center gap-3 p-3 rounded-xl border border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-900/20 transition-all">
                                <flux:avatar wire:key="vendor-avatar-{{ $courier_vendor['id'] ?? 'new' }}" src="{{ !empty($courier_vendor['image']) ? Storage::url($courier_vendor['image']) : '' }}" fallback="{{ substr($courier_vendor['name'] ?? '?', 0, 2) }}" class="shadow-sm w-10 h-10" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm leading-none truncate">{{ $courier_vendor['name'] ?? '' }}</h3>
                                    </div>
                                    <div class="mt-1 flex flex-col gap-y-1 text-[10px] text-zinc-600 dark:text-zinc-400">
                                        <span class="truncate">{{ $courier_vendor['phone'] ?: 'Tidak ada nomor telepon' }}</span>
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    <flux:button variant="subtle" size="sm" icon="x-mark" class="text-zinc-400 hover:text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-full w-8 h-8 flex items-center justify-center p-0" wire:click="clearCourierVendor" title="Hapus Vendor"></flux:button>
                                </div>
                            </div>
                        @else
                            <flux:button variant="subtle" class="w-full justify-center border-dashed border-2 border-zinc-300 dark:border-zinc-600 text-zinc-500 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="setSelectingFor('courier'); $dispatchTo('global.vendor-gallery-modal', 'set-filter-type', { type: 'Ekspedisi', locked: true })" x-on:click="$flux.modal('vendor-gallery-modal').show()" icon="users">
                                Pilih Vendor Ekspedisi
                            </flux:button>
                        @endif
                        @error('courier_vendor') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    @if($order && $order->status !== 'shipping' && $order->status !== 'completed')
                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700 mt-4">
                        <flux:checkbox wire:model="mark_as_shipped" label="Pindahkan pesanan ke kolom Pengiriman" description="Centang jika barang sudah siap dikirim." />
                    </div>
                    @endif
                </div>

                {{-- Kolom Kanan: Cropper Resi --}}
                <div class="pt-4 md:pt-0 md:pl-6 md:border-l border-zinc-200 dark:border-zinc-700">
                    <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Unggah Resi / Struk Asli Ekspedisi (Opsional)</label>
                    <div class="bg-white dark:bg-zinc-900 p-2 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <x-image-cropper id="shipping-cropper" wire:model="shipping_receipt_file" :image="$shipping_receipt_file && is_string($shipping_receipt_file) && !str_starts_with($shipping_receipt_file, 'data:image') ? Storage::url($shipping_receipt_file) : null" accept="image/*" />
                    </div>
                    <div wire:loading wire:target="shipping_receipt_file" class="text-xs text-orange-600 mt-2">Memproses gambar...</div>
                    @error('shipping_receipt_file') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="mt-6 bg-orange-50 dark:bg-orange-900/20 text-orange-800 dark:text-orange-200 text-xs p-3 rounded-lg flex gap-2 items-start border border-orange-200 dark:border-orange-900/50">
            <flux:icon.information-circle class="w-4 h-4 shrink-0 mt-0.5" />
            <p><strong>Info Penting:</strong> Menyimpan pengiriman akan merekap Biaya Ekspedisi ke nota dan memindahkan pesanan ke kolom <strong>Pengiriman</strong>.</p>
        </div>
        
        <div class="mt-6 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
            <flux:button variant="primary" wire:click="saveShippingInfo" wire:target="saveShippingInfo" wire:loading.attr="disabled" icon="paper-airplane">Kirim Pesanan</flux:button>
        </div>
    </div>
    @endif
</flux:modal>
