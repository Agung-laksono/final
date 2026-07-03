<?php
use function Livewire\Volt\{state, on, computed};
use Modules\Production\Models\ProductionOrder;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\Vendor;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'orderIds' => [],
    'vendor_id' => '',
    'vendor_name' => '',
    'phase_type' => 'finishing',
    'notes' => '',
    'costs' => [], // array keyed by item_id or order_id
    'global_cost' => null,
    'expected_delivery_date' => '',
    'is_grouped' => true,
    'item_notes' => [],
    'editingNoteKey' => null,
    'tempNoteContent' => '',
    'mediaUploadTemp' => null,
    'mediaUploadTempName' => null,
]);

$groupedOrders = computed(function () {
    if (empty($this->orderIds)) return collect();
    $orders = ProductionOrder::with('item')->whereIn('id', $this->orderIds)->get();
    
    $groups = [];
    foreach ($orders as $order) {
        if (!isset($groups[$order->item_id])) {
            $groups[$order->item_id] = [
                'item' => $order->item,
                'total_qty' => 0,
                'orders' => []
            ];
        }
        $groups[$order->item_id]['total_qty'] += $order->requested_qty;
        $groups[$order->item_id]['orders'][] = $order;
    }
    
    return $groups;
});

$ordersList = computed(function () {
    if (empty($this->orderIds)) return collect();
    return \Modules\Production\Models\ProductionOrder::with('item')->whereIn('id', $this->orderIds)->get();
});

on(['open-maklon-modal' => function ($orderIds = []) {
    $this->reset(['vendor_id', 'vendor_name', 'phase_type', 'notes', 'costs', 'global_cost']);
    $this->orderIds = $orderIds;
    
    // Inisialisasi struktur array costs agar @entangle di AlpineJS bisa bekerja
    // Harus membuat key untuk mode "grouped" maupun "single"
    $orders = ProductionOrder::whereIn('id', $this->orderIds)->get();
    
    $groups = [];
    foreach ($orders as $order) {
        // Inisialisasi untuk mode single
        $this->costs['single_'.$order->id] = null;
        
        // Kumpulkan untuk mode grouped
        if (!isset($groups[$order->item_id])) {
            $groups[$order->item_id] = true;
        }
    }
    
    // Inisialisasi untuk mode grouped
    foreach ($groups as $itemId => $val) {
        $this->costs['group_'.$itemId] = null;
    }
    
    $this->show = true;
}]);

$openEditor = function($key) {
    $this->editingNoteKey = $key;
    if ($key === 'global') {
        $this->tempNoteContent = $this->notes;
    } else {
        $this->tempNoteContent = $this->item_notes[$key] ?? '';
    }
    $this->dispatch('show-maklon-editor');
};

$saveEditor = function() {
    if ($this->editingNoteKey === 'global') {
        $this->notes = $this->tempNoteContent;
    } else {
        $this->item_notes[$this->editingNoteKey] = $this->tempNoteContent;
    }
    $this->dispatch('hide-maklon-editor');
};

$distributeGlobalCost = function() {
    $global = (float) $this->global_cost;
    if ($global <= 0) return;
    
    $totalQtyAll = 0;
    foreach ($this->groupedOrders as $group) {
        $totalQtyAll += $group['total_qty'];
    }
    
    if ($totalQtyAll > 0) {
        $costPerUnit = $global / $totalQtyAll;
        $newCosts = [];
        foreach ($this->groupedOrders as $itemId => $group) {
            $newCosts[$itemId] = round($costPerUnit * $group['total_qty']);
        }
        $this->costs = $newCosts;
    }
};

$copyDown = function($fromItemId) {
    $costToCopy = $this->costs[$fromItemId] ?? 0;
    $newCosts = $this->costs;
    foreach ($newCosts as $itemId => $val) {
        if ($itemId != $fromItemId && empty($val)) {
            $newCosts[$itemId] = $costToCopy;
        }
    }
    $this->costs = $newCosts;
};

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    // Bersihkan state costs dari key yang tidak relevan (stale data dari mode sebelumnnya)
    // agar tidak menggagalkan validasi.
    $validCosts = [];
    if ($this->is_grouped) {
        foreach ($this->groupedOrders as $itemId => $group) {
            $validCosts['group_'.$itemId] = $this->costs['group_'.$itemId] ?? null;
        }
    } else {
        foreach ($this->ordersList as $order) {
            $validCosts['single_'.$order->id] = $this->costs['single_'.$order->id] ?? null;
        }
    }
    $this->costs = $validCosts;

    $this->validate([
        'vendor_id' => 'required',
        'costs.*' => 'required|numeric|min:0'
    ], [
        'vendor_id.required' => 'Pilih vendor terlebih dahulu.',
        'costs.*.required' => 'Biaya harus diisi.',
        'costs.*.numeric' => 'Biaya tidak valid.'
    ]);

    DB::transaction(function () {
        $vendor = Vendor::find($this->vendor_id);
        if (!$vendor) return;

        // Create PO
        $nextId = PurchaseOrder::max('id') + 1;
        $poNumber = 'GUNJAS-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $totalAmount = array_sum($this->costs);

        $po = PurchaseOrder::create([
            'po_number' => $poNumber,
            'vendor_id' => $vendor->id,
            'order_date' => now(),
            'status' => 'ordered',
            'total_amount' => $totalAmount,
            'notes' => "Perintah Kerja Maklon/Jasa. " . $this->notes,
            'created_by' => auth()->id()
        ]);

        if ($this->is_grouped) {
            foreach ($this->groupedOrders as $itemId => $group) {
                $groupCost = $this->costs['group_'.$itemId] ?? 0;
                $groupTotalQty = max(1, $group['total_qty']);
                $costPerUnit = $groupCost / $groupTotalQty;

                $itemNote = $this->item_notes['group_'.$itemId] ?? '';
                $finalNotes = "<p><strong>Jasa Maklon Fase:</strong> " . ucfirst($this->phase_type) . "</p>";
                if (!empty($itemNote)) {
                    $finalNotes .= $itemNote;
                }

                // Create PO Item
                $po->items()->create([
                    'item_id' => $itemId, // we use the finished good item id
                    'quantity' => $groupTotalQty,
                    'unit_price' => $costPerUnit, // cost per item
                    'subtotal' => $groupCost,
                    'notes' => $finalNotes
                ]);

                // Update Production Orders
                foreach ($group['orders'] as $order) {
                    $orderCost = $costPerUnit * $order->requested_qty;
                    
                    $order->status = 'in_production';
                    $order->phase_type = $this->phase_type;
                    $order->vendor_cost = $orderCost;
                    $order->purchase_order_id = $po->id;
                    if ($this->notes) {
                        $order->notes = $order->notes . "\n[Maklon to " . $vendor->name . "]: " . $this->notes;
                    }
                    $order->save();
                }
            }
        } else {
            foreach ($this->ordersList as $order) {
                $orderCost = $this->costs['single_'.$order->id] ?? 0;
                $costPerUnit = $orderCost / max(1, $order->requested_qty);

                $itemNote = $this->item_notes['single_'.$order->id] ?? '';
                $finalNotes = "<p><strong>Jasa Maklon Fase:</strong> " . ucfirst($this->phase_type) . " (Ref: " . $order->production_req_number . ")</p>";
                if (!empty($itemNote)) {
                    $finalNotes .= $itemNote;
                }

                // Create PO Item
                $po->items()->create([
                    'item_id' => $order->item_id,
                    'quantity' => $order->requested_qty,
                    'unit_price' => $costPerUnit,
                    'subtotal' => $orderCost,
                    'notes' => $finalNotes
                ]);

                // Update Production Order
                $order->status = 'in_production';
                $order->phase_type = $this->phase_type;
                $order->vendor_cost = $orderCost;
                $order->purchase_order_id = $po->id;
                if ($this->notes) {
                    $order->notes = $order->notes . "\n[Maklon to " . $vendor->name . "]: " . $this->notes;
                }
                $order->save();
            }
        }
    });

    $this->dispatch('maklon-po-created');
    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('production_order');
    $this->show = false;
    \Flux::toast('Perintah Kerja Maklon berhasil dibuat!', variant: 'success');
};

$updatedMediaUploadTemp = function ($value) {
    if ($value) {
        $imageParts = explode(";base64,", $value);
        if (count($imageParts) == 2) {
            $imageTypeAux = explode("image/", $imageParts[0]);
            $imageType = $imageTypeAux[1];
            $imageBase64 = base64_decode($imageParts[1]);
            
            // Format nama: nama_asli_timestamp.webp
            $originalName = $this->mediaUploadTempName ? pathinfo($this->mediaUploadTempName, PATHINFO_FILENAME) : 'cropped';
            $slugName = \Illuminate\Support\Str::slug($originalName);
            $fileName = $slugName . '_' . time() . '.' . $imageType;
            
            $path = 'maklon-media/' . date('Y/m') . '/' . $fileName;
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $imageBase64);
            
            $this->mediaUploadTemp = null;
            $this->mediaUploadTempName = null;
            $this->dispatch('media-uploaded-success');
        }
    }
};

$handleVendorSelected = function ($vendorId) {
    $vendor = Vendor::find($vendorId);
    if ($vendor) {
        $this->vendor_id = $vendor->id;
        $this->vendor_name = $vendor->name;
    }
};

?>
<div @vendor-selected.window="$wire.handleVendorSelected($event.detail.vendorId); setTimeout(() => { $flux.modal('vendor-gallery-modal').close() }, 50);">

<!-- STANDARD CUSTOM MODAL (Menggantikan flux:modal untuk menghindari bentrok native <dialog>) -->
<div x-data="{
        show: $wire.entangle('show'),
        showEditor: false,

        init() {
            if (this.show) document.body.classList.add('overflow-hidden');

            window.addEventListener('show-maklon-editor', () => {
                this.showEditor = true;
            });
            
            window.addEventListener('hide-maklon-editor', () => {
                this.showEditor = false;
            });

            this.$watch('show', value => {
                if (!value) {
                    this.showEditor = false;
                    document.body.classList.remove('overflow-hidden');
                } else {
                    document.body.classList.add('overflow-hidden');
                }
            });
        }
    }"  

    x-show="show" 
    style="display: none;" 
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
    @keydown.escape.window="show = false">

    
    <!-- Modal Backdrop -->
    <div x-show="show" 
         x-transition.opacity.duration.300ms 
         class="absolute inset-0 bg-zinc-900/50 dark:bg-zinc-900/80 backdrop-blur-sm" 
         @click="show = false"></div>

    <!-- MAIN MODAL CONTAINER -->
    <div x-show="show" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="relative bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full max-w-[800px] h-[90vh] overflow-hidden flex flex-col">
        
        <!-- CONTAINER TANPA SLIDER (Murni Toggle x-show): Mencegah bug compositing & reflow pada background browser -->
        <div class="flex-grow relative w-full h-full">
             
            <!-- PANEL 1: KONTEN NORMAL -->
            <div x-show="!showEditor" class="absolute inset-0 flex flex-col w-full h-full">
                <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Buat Perintah Kerja Maklon</flux:heading>
            <flux:subheading>Kirim barang-barang ini ke vendor eksternal dan buat tagihannya.</flux:subheading>
        </div>

        @if ($errors->any())
            <div class="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-3 rounded-lg text-sm border border-red-200 dark:border-red-800">
                <strong>Gagal Menyimpan:</strong> Mohon periksa kembali inputan Anda (seperti Vendor atau Biaya).
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <flux:label>Pilih Vendor Maklon</flux:label>
                <div class="flex gap-2 mt-1">
                    <flux:input wire:model="vendor_name" readonly placeholder="Pilih Vendor dari Galeri ->" class="flex-1 bg-zinc-50" />
                    <flux:button variant="filled" icon="users" x-on:click="setTimeout(() => { $flux.modal('vendor-gallery-modal').show() }, 50)">Galeri</flux:button>
                </div>
                @error('vendor_id')
                    <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:select wire:model="phase_type" label="Fase Pengerjaan">
                        <option value="finishing">Finishing</option>
                        <option value="jok">Jok (Upholstery)</option>
                        <option value="rakit">Rakit (Assembly)</option>
                    </flux:select>
                </div>
                <div>
                    <flux:input type="date" wire:model="expected_delivery_date" label="Tenggat Waktu (Deadline)" />
                </div>
            </div>

            <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                    <div class="flex items-center gap-4">
                        <flux:heading size="md">Daftar Barang & Biaya Borongan</flux:heading>
                        <flux:switch wire:model.live="is_grouped" label="Gabungkan Item Serupa" />
                    </div>
                    <div class="w-full sm:w-72">
                        <x-currency-input wire:model="global_cost" placeholder="Total Biaya Global..." class="!bg-yellow-50 dark:!bg-yellow-900/20" />
                        <div class="mt-1 flex justify-end">
                            <flux:button size="xs" variant="subtle" wire:click="distributeGlobalCost" icon="arrows-right-left">Bagi Rata Proporsional</flux:button>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    @if($this->is_grouped)
                        @foreach($this->groupedOrders as $itemId => $group)
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 bg-zinc-50 dark:bg-zinc-800/30 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <div class="flex-1 group" x-data="{ open: false }">
                                    <div class="flex items-center gap-2 relative">
                                        <div class="font-bold text-sm">{{ $group['item']->name }}</div>
                                        <flux:button size="sm" variant="subtle" class="px-1 py-0 h-6 shrink-0 text-zinc-400 hover:text-indigo-600 opacity-50 group-hover:opacity-100 transition-opacity" x-on:click="open = !open" tooltip="Tulis Catatan">
                                            <flux:icon.pencil-square class="w-4 h-4" />
                                        </flux:button>
                                        
                                        <!-- Popover Catatan -->
                                        <div x-show="open" x-transition x-on:click.outside="open = false" style="display: none;" class="absolute left-0 top-full mt-1 w-64 sm:w-80 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 shadow-xl rounded-xl p-3 z-50">
                                            <div x-data="{
                                                get isRichText() {
                                                    const val = $wire.item_notes['group_{{ $itemId }}'] || '';
                                                    return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img');
                                                }
                                            }">
                                                <!-- Jika terdeteksi HTML (Rich Text) -->
                                                <div x-show="isRichText" x-cloak class="relative cursor-pointer group" wire:click="openEditor('group_{{ $itemId }}')">
                                                    <div class="w-full min-h-[3rem] max-h-[8rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 bg-zinc-50 dark:bg-zinc-800/50 text-sm prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200" x-html="$wire.item_notes['group_{{ $itemId }}']">
                                                    </div>
                                                    <div class="absolute inset-0 bg-white/50 dark:bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center backdrop-blur-[1px]">
                                                        <flux:button size="xs" variant="primary" icon="pencil-square">Edit Catatan</flux:button>
                                                    </div>
                                                </div>

                                                <!-- Quick Note (Teks Biasa) -->
                                                <div x-show="!isRichText" class="relative">
                                                    <flux:textarea wire:model="item_notes.group_{{ $itemId }}" placeholder="Catatan sederhana..." rows="2" />
                                                    <button type="button" wire:click="openEditor('group_{{ $itemId }}')" class="absolute right-2 top-2 p-1 text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 bg-white dark:bg-zinc-900 rounded" title="Buka Editor Lengkap">
                                                        <flux:icon.arrows-pointing-out class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-zinc-500 mt-0.5">
                                        Digabungkan dari {{ count($group['orders']) }} pesanan • Total Qty: <strong class="text-zinc-800 dark:text-zinc-200">{{ $group['total_qty'] }}</strong>
                                    </div>
                                    <div class="text-xs mt-1 cursor-pointer hover:text-indigo-600 transition-colors" x-on:click="open = true">
                                        <div x-show="!!$wire.item_notes['group_{{ $itemId }}']" x-cloak class="text-zinc-600 dark:text-zinc-300 line-clamp-1 prose prose-xs prose-p:my-0 prose-img:hidden" x-html="$wire.item_notes['group_{{ $itemId }}']"></div>
                                        <div x-show="!$wire.item_notes['group_{{ $itemId }}']" class="text-zinc-400 dark:text-zinc-500 italic line-clamp-1">— Tidak ada catatan khusus.</div>
                                    </div>
                                </div>
                                <div class="w-full sm:w-64 shrink-0 flex gap-2">
                                    <div class="relative flex-1">
                                        <x-currency-input wire:model="costs.group_{{ $itemId }}" placeholder="0" />
                                        @error('costs.group_'.$itemId)
                                            <div class="text-xs text-red-500 mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        @foreach($this->ordersList as $order)
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 bg-zinc-50 dark:bg-zinc-800/30 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <div class="flex-1 group" x-data="{ open: false }">
                                    <div class="flex items-center gap-2 relative">
                                        <div class="font-bold text-sm">{{ $order->item->name }}</div>
                                        <flux:button size="sm" variant="subtle" class="px-1 py-0 h-6 shrink-0 text-zinc-400 hover:text-indigo-600 opacity-50 group-hover:opacity-100 transition-opacity" x-on:click="open = !open" tooltip="Tulis Catatan">
                                            <flux:icon.pencil-square class="w-4 h-4" />
                                        </flux:button>
                                        
                                        <!-- Popover Catatan -->
                                        <div x-show="open" x-transition x-on:click.outside="open = false" style="display: none;" class="absolute left-0 top-full mt-1 w-64 sm:w-80 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 shadow-xl rounded-xl p-3 z-50">
                                            <div x-data="{
                                                get isRichText() {
                                                    const val = $wire.item_notes['single_{{ $order->id }}'] || '';
                                                    return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img');
                                                }
                                            }">
                                                <!-- Jika terdeteksi HTML (Rich Text) -->
                                                <div x-show="isRichText" x-cloak class="relative cursor-pointer group" wire:click="openEditor('single_{{ $order->id }}')">
                                                    <div class="w-full min-h-[3rem] max-h-[8rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 bg-zinc-50 dark:bg-zinc-800/50 text-sm prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200" x-html="$wire.item_notes['single_{{ $order->id }}']">
                                                    </div>
                                                    <div class="absolute inset-0 bg-white/50 dark:bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center backdrop-blur-[1px]">
                                                        <flux:button size="xs" variant="primary" icon="pencil-square">Edit Catatan</flux:button>
                                                    </div>
                                                </div>

                                                <!-- Quick Note (Teks Biasa) -->
                                                <div x-show="!isRichText" class="relative">
                                                    <flux:textarea wire:model="item_notes.single_{{ $order->id }}" placeholder="Catatan sederhana..." rows="2" />
                                                    <button type="button" wire:click="openEditor('single_{{ $order->id }}')" class="absolute right-2 top-2 p-1 text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 bg-white dark:bg-zinc-900 rounded" title="Buka Editor Lengkap">
                                                        <flux:icon.arrows-pointing-out class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-zinc-500 mt-0.5">
                                        Ref: {{ $order->production_req_number }} • Qty: <strong class="text-zinc-800 dark:text-zinc-200">{{ $order->requested_qty }}</strong>
                                    </div>
                                    <div class="text-xs mt-1 cursor-pointer hover:text-indigo-600 transition-colors" x-on:click="open = true">
                                        <div x-show="!!$wire.item_notes['single_{{ $order->id }}']" x-cloak class="text-zinc-600 dark:text-zinc-300 line-clamp-1 prose prose-xs prose-p:my-0 prose-img:hidden" x-html="$wire.item_notes['single_{{ $order->id }}']"></div>
                                        <div x-show="!$wire.item_notes['single_{{ $order->id }}']" class="text-zinc-400 dark:text-zinc-500 italic line-clamp-1">— Tidak ada catatan khusus.</div>
                                    </div>
                                </div>
                                <div class="w-full sm:w-64 shrink-0 flex gap-2">
                                    <div class="relative flex-1">
                                        <x-currency-input wire:model="costs.single_{{ $order->id }}" placeholder="0" />
                                        @error('costs.single_'.$order->id)
                                            <div class="text-xs text-red-500 mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="mt-4" x-data="{
                get isRichText() {
                    const val = $wire.notes || '';
                    return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img');
                }
            }">
                <div class="flex items-center justify-between mb-2">
                    <flux:label>Catatan Jasa (Global)</flux:label>
                    <flux:button size="xs" variant="subtle" icon="document-text" x-on:click="$flux.modal('template-modal').show()">Gunakan Template</flux:button>
                </div>
                
                <!-- Jika terdeteksi HTML -->
                <div x-show="isRichText" x-cloak class="relative cursor-pointer group" wire:click="openEditor('global')">
                    <div class="w-full min-h-[5rem] max-h-[12rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 bg-white dark:bg-zinc-900/50 text-sm prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200 prose-img:rounded-xl" x-html="$wire.notes">
                    </div>
                    <div class="absolute inset-0 bg-white/50 dark:bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center backdrop-blur-[1px]">
                        <flux:button size="sm" variant="primary" icon="pencil-square">Buka Editor Lengkap</flux:button>
                    </div>
                </div>
                
                <!-- Quick Note -->
                <div x-show="!isRichText" class="relative">
                    <flux:textarea wire:model="notes" placeholder="Tulis instruksi atau catatan khusus vendor..." rows="3" />
                    <button type="button" wire:click="openEditor('global')" class="absolute right-2 top-2 p-1 text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 bg-white dark:bg-zinc-900 rounded" title="Buka Editor Lengkap">
                        <flux:icon.arrows-pointing-out class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800 mt-4">
            <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
            <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Simpan & Buat Tagihan</span>
                <span wire:loading wire:target="save">Memproses...</span>
            </flux:button>
        </div>
    </div> <!-- End space-y-6 -->
</div> <!-- End p-6 -->
</div> <!-- END PANEL -->

<!-- PANEL 2: KONTEN RICH EDITOR -->
<div x-show="showEditor" style="display: none;" class="absolute inset-0 flex flex-col w-full h-full bg-slate-50 dark:bg-zinc-900/50 border-l border-zinc-200 dark:border-zinc-800 z-10">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900 shrink-0">
        <div>
            <h3 class="font-semibold text-lg text-zinc-800 dark:text-zinc-100 uppercase tracking-widest">EDITOR CATATAN</h3>
            <p class="text-[10px] text-zinc-400 tracking-wider uppercase mt-0.5">RICH TEXT MODE - Gunakan tombol Template untuk menyisipkan format standar</p>
        </div>
        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="subtle" icon="document-text" x-on:click="$dispatch('open-template-modal')" class="hidden md:flex">Template</flux:button>
            <flux:button size="sm" variant="subtle" icon="document-text" x-on:click="$dispatch('open-template-modal')" class="md:hidden" />
            
            <button wire:click="$dispatch('hide-maklon-editor')" type="button" class="text-zinc-400 hover:text-rose-500 transition-colors p-2 rounded-full hover:bg-rose-50 dark:hover:bg-rose-900/20">
                <flux:icon.x-mark class="w-5 h-5" />
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-hidden relative p-4" wire:ignore>
        <x-rich-editor wire:model="tempNoteContent" height="100%" />
    </div>

    <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex justify-end gap-2 shrink-0">
        <flux:button variant="ghost" wire:click="$dispatch('hide-maklon-editor')">Batal</flux:button>
        <flux:button variant="primary" wire:click="saveEditor">Simpan Catatan</flux:button>
    </div>
</div> <!-- END PANEL 2 -->

        </div> <!-- END CONTAINER TANPA SLIDER -->
    </div> <!-- END OF MAIN MODAL CONTAINER -->
</div> <!-- END OF X-DATA MODAL WRAPPER -->

<livewire:global.vendor-gallery-modal />
<livewire:global.vendor-form-modal />
<livewire:work-order.price-history-modal />
<livewire:global.template-modal />

@once
<style>
    /* TinyMCE: pastikan editor mengisi container flex sepenuhnya */
    #tinymce-wrapper .tox-tinymce { height: 100% !important; border: none !important; }
    /* Fix z-index popup TinyMCE (table menu, dll) agar tidak tertutup modal */
    .tox-tinymce-aux { z-index: 999999 !important; }
</style>
<script>
document.addEventListener('focusin', function (e) {
    if (e.target.closest('.tox-tinymce-aux, .moxman-window') !== null) {
        e.stopImmediatePropagation();
    }
});
</script>
@endonce

</div>
