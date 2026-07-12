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

$selectedVendor = computed(function () {
    if (!$this->vendor_id) return null;
    return Vendor::find($this->vendor_id);
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
    
    if ($this->is_grouped) {
        foreach ($this->groupedOrders as $group) {
            $totalQtyAll += $group['total_qty'];
        }
        
        if ($totalQtyAll > 0) {
            $costPerUnit = $global / $totalQtyAll;
            $newCosts = $this->costs;
            foreach ($this->groupedOrders as $itemId => $group) {
                $newCosts['group_'.$itemId] = round($costPerUnit * $group['total_qty']);
            }
            $this->costs = $newCosts;
        }
    } else {
        foreach ($this->ordersList as $order) {
            $totalQtyAll += $order->requested_qty;
        }
        
        if ($totalQtyAll > 0) {
            $costPerUnit = $global / $totalQtyAll;
            $newCosts = $this->costs;
            foreach ($this->ordersList as $order) {
                $newCosts['single_'.$order->id] = round($costPerUnit * $order->requested_qty);
            }
            $this->costs = $newCosts;
        }
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
        'expected_delivery_date' => 'required|date',
        'costs.*' => 'required|numeric|min:0'
    ], [
        'vendor_id.required' => 'Pilih vendor terlebih dahulu.',
        'expected_delivery_date.required' => 'Tenggat waktu pengerjaan wajib diisi.',
        'expected_delivery_date.date' => 'Tenggat waktu pengerjaan harus berupa tanggal yang valid.',
        'costs.*.required' => 'Biaya harus diisi.',
        'costs.*.numeric' => 'Biaya tidak valid.'
    ]);

    $poData = DB::transaction(function () {
        $vendor = Vendor::find($this->vendor_id);
        if (!$vendor) return null;

        // Create PO
        $nextId = PurchaseOrder::max('id') + 1;
        $poNumber = 'GUNJAS-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $totalAmount = array_sum($this->costs);

        $po = PurchaseOrder::create([
            'po_number' => $poNumber,
            'vendor_id' => $vendor->id,
            'order_date' => now(),
            'expected_delivery_date' => $this->expected_delivery_date,
            'status' => 'ordered',
            'total_amount' => $totalAmount,
            'notes' => "Perintah Kerja Vendor/Jasa. Tenggat Waktu: " . $this->expected_delivery_date . ".\n\n" . $this->notes,
            'created_by' => auth()->id()
        ]);

        if ($this->is_grouped) {
            foreach ($this->groupedOrders as $itemId => $group) {
                $groupCost = $this->costs['group_'.$itemId] ?? 0;
                $groupTotalQty = max(1, $group['total_qty']);
                $costPerUnit = $groupCost / $groupTotalQty;

                $itemNote = $this->item_notes['group_'.$itemId] ?? '';
                $finalNotes = "<p><strong>Jasa Vendor Fase:</strong> " . ucfirst($this->phase_type) . "</p>";
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
                $finalNotes = "<p><strong>Jasa Vendor Fase:</strong> " . ucfirst($this->phase_type) . " (Ref: " . $order->production_req_number . ")</p>";
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
        return $po;
    });

    $this->dispatch('maklon-po-created');
    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('production_order');
    $this->show = false;
    
    if ($poData) {
        \Flux::toast('SPK ' . $poData->po_number . ' berhasil dibuat!', variant: 'success');
        // Langsung buka modal GUNJAS yang baru saja dibuat
        $this->dispatch('open-po-detail-modal', poId: $poData->id);
    } else {
        \Flux::toast('Perintah Kerja Vendor berhasil dibuat!', variant: 'success');
    }
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
            <flux:heading size="lg">Buat Perintah Kerja Vendor</flux:heading>
            <flux:subheading>Kirim barang-barang ini ke vendor eksternal dan buat tagihannya.</flux:subheading>
        </div>

        @if ($errors->any())
            <div class="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-3 rounded-lg text-sm border border-red-200 dark:border-red-800">
                <strong>Gagal Menyimpan:</strong> Mohon periksa kembali inputan Anda (seperti Vendor atau Biaya).
            </div>
        @endif

        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <div>
                <div class="flex items-center justify-between mb-2">
                    <flux:label>Vendor Eksternal Terpilih <span class="text-red-500">*</span></flux:label>
                </div>
                
                @if($this->selectedVendor)
                    <div class="relative overflow-hidden bg-white dark:bg-zinc-900 border-2 {{ $errors->has('vendor_id') ? 'border-red-500' : 'border-indigo-500/50' }} rounded-xl p-4 shadow-sm transition-all hover:shadow-md group h-full flex flex-col justify-center">
                        <!-- Background decoration -->
                        <div class="absolute -right-12 -top-12 w-32 h-32 bg-indigo-50 dark:bg-indigo-900/20 rounded-full blur-2xl group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/30 transition-colors"></div>
                        
                        <div class="relative flex items-start gap-3">
                            <!-- Vendor Avatar -->
                            <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 border-2 border-white dark:border-zinc-700 shadow-sm flex items-center justify-center shrink-0 overflow-hidden">
                                @if($this->selectedVendor->image)
                                    <img src="{{ Storage::url($this->selectedVendor->image) }}" class="w-full h-full object-cover" alt="{{ $this->selectedVendor->name }}" />
                                @else
                                    <flux:icon.building-storefront class="w-6 h-6 text-zinc-400" />
                                @endif
                            </div>
                            
                            <!-- Vendor Info & Action -->
                            <div class="flex-1 min-w-0 flex flex-col gap-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-0.5">
                                            <h3 class="font-bold text-zinc-900 dark:text-white text-base truncate">{{ $this->selectedVendor->name }}</h3>
                                            <span class="shrink-0 inline-flex items-center rounded-md bg-indigo-50 dark:bg-indigo-400/10 px-2 py-0.5 text-[10px] font-medium text-indigo-700 dark:text-indigo-400 ring-1 ring-inset ring-indigo-700/10 dark:ring-indigo-400/30">
                                                {{ $this->selectedVendor->type ?? 'Vendor' }}
                                            </span>
                                        </div>
                                        <div class="flex flex-col gap-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                            <span class="flex items-center gap-1.5 truncate">
                                                <flux:icon.phone class="w-3 h-3 shrink-0" />
                                                <span class="truncate">{{ $this->selectedVendor->phone ?? 'Tidak ada telepon' }}</span>
                                            </span>
                                            <span class="flex items-center gap-1.5 truncate">
                                                <flux:icon.map-pin class="w-3 h-3 shrink-0" />
                                                <span class="truncate">{{ $this->selectedVendor->city ? $this->selectedVendor->city . ', ' : '' }} {{ $this->selectedVendor->province ?? 'Lokasi tak diketahui' }}</span>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <div class="shrink-0 mt-1">
                                        <flux:button size="sm" variant="subtle" icon="users" x-on:click="setTimeout(() => { $flux.modal('vendor-gallery-modal').show() }, 50)" class="shadow-sm !px-2.5">Ganti</flux:button>
                                    </div>
                                </div>
                                
                                @if($this->selectedVendor->address)
                                    <div class="text-xs text-zinc-600 dark:text-zinc-400 flex items-start gap-1.5 bg-zinc-50 dark:bg-zinc-800/50 p-2 rounded-lg border border-zinc-100 dark:border-zinc-700/50">
                                        <flux:icon.map class="w-3.5 h-3.5 shrink-0 text-zinc-400 mt-0.5" />
                                        <span class="leading-relaxed line-clamp-2 italic">{{ $this->selectedVendor->address }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div x-on:click="setTimeout(() => { $flux.modal('vendor-gallery-modal').show() }, 50)" class="cursor-pointer group flex flex-col items-center justify-center p-6 border-2 border-dashed {{ $errors->has('vendor_id') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-zinc-300 dark:border-zinc-700 hover:border-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/20' }} rounded-xl transition-all duration-200 h-full">
                        <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/50 flex items-center justify-center mb-3 transition-colors">
                            <flux:icon.user-plus class="w-6 h-6 text-zinc-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors" />
                        </div>
                        <div class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-indigo-700 dark:group-hover:text-indigo-400 transition-colors">Ketuk untuk Memilih Vendor</div>
                        <div class="text-xs text-zinc-500 mt-1 text-center max-w-xs">Pilih vendor eksternal dari galeri yang akan mengerjakan pesanan ini.</div>
                    </div>
                @endif
                
                @error('vendor_id')
                    <div class="text-sm text-red-500 mt-2 flex items-center gap-1"><flux:icon.exclamation-circle class="w-4 h-4" /> {{ $message }}</div>
                @enderror
            </div>

            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between mb-2">
                    <flux:label>Tenggat Waktu Pengerjaan <span class="text-red-500">*</span></flux:label>
                </div>
                <div class="flex-1 bg-white dark:bg-zinc-900 border-2 border-zinc-200 dark:border-zinc-700 rounded-xl p-4 flex flex-col justify-center relative overflow-hidden group hover:border-indigo-400 dark:hover:border-indigo-500/50 transition-colors shadow-sm" x-data="{
                    days: '',
                    dateVal: $wire.entangle('expected_delivery_date'),
                    getLocalDateStr(dateObj) {
                        const tzOffset = dateObj.getTimezoneOffset() * 60000;
                        return new Date(dateObj.getTime() - tzOffset).toISOString().split('T')[0];
                    },
                    get minDate() {
                        return this.getLocalDateStr(new Date());
                    },
                    init() {
                        this.calculateDays();
                        this.$watch('dateVal', () => this.calculateDays());
                    },
                    calculateDays() {
                        if (!this.dateVal) { this.days = ''; return; }
                        const today = new Date();
                        today.setHours(0,0,0,0);
                        const target = new Date(this.dateVal);
                        target.setHours(0,0,0,0);
                        const diffTime = target.getTime() - today.getTime();
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        this.days = diffDays >= 0 ? diffDays : '';
                    },
                    updateFromDays() {
                        if (this.days === '' || this.days < 0) {
                            this.dateVal = '';
                            return;
                        }
                        const d = new Date();
                        d.setDate(d.getDate() + parseInt(this.days));
                        this.dateVal = this.getLocalDateStr(d);
                    }
                }">
                    <div class="relative z-10 flex flex-col gap-3">
                        <!-- Pilihan Hari -->
                        <div class="flex items-center gap-3">
                            <div class="w-20">
                                <flux:input type="text" inputmode="numeric" x-model="days" @input="days = $event.target.value.replace(/\D/g, ''); updateFromDays()" placeholder="0" class="!text-center !text-lg !font-bold text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400 leading-tight">
                                Hari dari <br/>sekarang
                            </div>
                        </div>

                        <div class="flex items-center gap-3 py-1">
                            <div class="flex-1 border-t border-zinc-200 dark:border-zinc-800"></div>
                            <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">ATAU</div>
                            <div class="flex-1 border-t border-zinc-200 dark:border-zinc-800"></div>
                        </div>

                        <!-- Pilihan Tanggal -->
                        <div @click="const inp = $el.querySelector('input'); if (inp && inp.showPicker) { try { inp.showPicker() } catch(e){} }">
                            <flux:input type="date" x-model="dateVal" x-bind:min="minDate" icon="calendar" class="!bg-zinc-50 dark:!bg-zinc-800/50 cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:w-0" />
                        </div>
                    </div>
                </div>
                @error('expected_delivery_date')
                    <div class="text-sm text-red-500 mt-2 flex items-center gap-1"><flux:icon.exclamation-circle class="w-4 h-4" /> {{ $message }}</div>
                @enderror
            </div>
            </div>

            <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="md">Daftar Barang & Biaya Borongan</flux:heading>
                    <flux:switch wire:model.live="is_grouped" label="Gabungkan Item Serupa" />
                </div>
                
                <div class="space-y-3">
                    @if($this->is_grouped)
                        @foreach($this->groupedOrders as $itemId => $group)
                            <div wire:key="group-row-{{ $itemId }}" class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 bg-zinc-50 dark:bg-zinc-800/30 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <div class="flex-1 min-w-0 group" x-data="{ open: false }">
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
                                                    return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img') || val.includes('<table') || val.includes('<ul') || val.includes('<ol');
                                                }
                                            }">
                                                <div class="flex justify-between items-center mb-2">
                                                    <h3 class="text-[11px] font-bold text-slate-400 tracking-wider uppercase">CATATAN ITEM</h3>
                                                    <flux:button size="xs" variant="subtle" icon="arrows-pointing-out" class="!px-2 h-7" wire:click="openEditor('group_{{ $itemId }}')" title="Buka Editor Lengkap">Editor Lengkap</flux:button>
                                                </div>

                                                <!-- Jika terdeteksi HTML (Rich Text) -->
                                                <div x-show="isRichText" x-cloak class="relative group" wire:click="openEditor('group_{{ $itemId }}')">
                                                    <div class="w-full min-h-[3rem] max-h-[8rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 bg-zinc-50 dark:bg-zinc-800/50 text-sm prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200 cursor-pointer" x-html="$wire.item_notes['group_{{ $itemId }}']">
                                                    </div>
                                                </div>

                                                <!-- Quick Note (Teks Biasa) -->
                                                <div x-show="!isRichText">
                                                    <flux:textarea wire:model="item_notes.group_{{ $itemId }}" placeholder="Catatan sederhana..." rows="2" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-zinc-500 mt-0.5">
                                        Digabungkan dari {{ count($group['orders']) }} pesanan • Total Qty: <strong class="text-zinc-800 dark:text-zinc-200">{{ $group['total_qty'] }}</strong>
                                    </div>
                                    <div class="text-xs mt-1 cursor-pointer hover:text-indigo-600 transition-colors" x-on:click="open = true">
                                        <div x-show="!!$wire.item_notes['group_{{ $itemId }}']" x-cloak class="text-zinc-600 dark:text-zinc-300 line-clamp-1 break-all prose prose-xs prose-p:my-0 prose-img:hidden" x-html="$wire.item_notes['group_{{ $itemId }}']"></div>
                                        <div x-show="!$wire.item_notes['group_{{ $itemId }}']" class="text-zinc-400 dark:text-zinc-500 italic line-clamp-1 break-all">— Tidak ada catatan khusus.</div>
                                    </div>
                                </div>
                                <div class="w-full sm:w-64 shrink-0 flex gap-2">
                                    <div class="relative flex-1">
                                        <x-currency-input wire:key="group-cost-{{ $itemId }}" wire:model="costs.group_{{ $itemId }}" placeholder="0" />
                                        @error('costs.group_'.$itemId)
                                            <div class="text-xs text-red-500 mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        @foreach($this->ordersList as $order)
                            <div wire:key="single-row-{{ $order->id }}" class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 bg-zinc-50 dark:bg-zinc-800/30 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <div class="flex-1 min-w-0 group" x-data="{ open: false }">
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
                                                    return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img') || val.includes('<table') || val.includes('<ul') || val.includes('<ol');
                                                }
                                            }">
                                                <div class="flex justify-between items-center mb-2">
                                                    <h3 class="text-[11px] font-bold text-slate-400 tracking-wider uppercase">CATATAN ITEM</h3>
                                                    <flux:button size="xs" variant="subtle" icon="arrows-pointing-out" class="!px-2 h-7" wire:click="openEditor('single_{{ $order->id }}')" title="Buka Editor Lengkap">Editor Lengkap</flux:button>
                                                </div>
                                                
                                                <!-- Jika terdeteksi HTML (Rich Text) -->
                                                <div x-show="isRichText" x-cloak class="relative group" wire:click="openEditor('single_{{ $order->id }}')">
                                                    <div class="w-full min-h-[3rem] max-h-[8rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 bg-zinc-50 dark:bg-zinc-800/50 text-sm prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200 cursor-pointer" x-html="$wire.item_notes['single_{{ $order->id }}']">
                                                    </div>
                                                </div>

                                                <!-- Quick Note (Teks Biasa) -->
                                                <div x-show="!isRichText">
                                                    <flux:textarea wire:model="item_notes.single_{{ $order->id }}" placeholder="Catatan sederhana..." rows="2" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-zinc-500 mt-0.5">
                                        Ref: {{ $order->production_req_number }} • Qty: <strong class="text-zinc-800 dark:text-zinc-200">{{ $order->requested_qty }}</strong>
                                    </div>
                                    <div class="text-xs mt-1 cursor-pointer hover:text-indigo-600 transition-colors" x-on:click="open = true">
                                        <div x-show="!!$wire.item_notes['single_{{ $order->id }}']" x-cloak class="text-zinc-600 dark:text-zinc-300 line-clamp-1 break-all prose prose-xs prose-p:my-0 prose-img:hidden" x-html="$wire.item_notes['single_{{ $order->id }}']"></div>
                                        <div x-show="!$wire.item_notes['single_{{ $order->id }}']" class="text-zinc-400 dark:text-zinc-500 italic line-clamp-1 break-all">— Tidak ada catatan khusus.</div>
                                    </div>
                                </div>
                                <div class="w-full sm:w-64 shrink-0 flex gap-2">
                                    <div class="relative flex-1">
                                        <x-currency-input wire:key="single-cost-{{ $order->id }}" wire:model="costs.single_{{ $order->id }}" placeholder="0" />
                                        @error('costs.single_'.$order->id)
                                            <div class="text-xs text-red-500 mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <!-- Grand Total Display -->
                <div class="mt-4 flex flex-col sm:flex-row justify-between items-center p-4 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-inner" 
                     x-data="{
                        get grandTotal() {
                            let total = 0;
                            const costs = $wire.costs || {};
                            for (const key in costs) {
                                const val = parseFloat(costs[key]) || 0;
                                total += val;
                            }
                            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(total);
                        }
                     }">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-wider mb-2 sm:mb-0">
                        Total Estimasi Biaya:
                    </div>
                    <div class="text-2xl font-black text-indigo-700 dark:text-indigo-400 tracking-tight" x-text="grandTotal">
                        Rp 0
                    </div>
                </div>

                <!-- Alat Bantu Bagi Rata Proporsional -->
                <div class="mt-4 p-4 bg-indigo-50/50 dark:bg-indigo-900/10 border border-indigo-100 dark:border-indigo-900/30 rounded-xl flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-sm text-indigo-800 dark:text-indigo-400">
                        <div class="font-bold flex items-center gap-2 mb-0.5">
                            <flux:icon.calculator class="w-4 h-4" />
                            Alat Bantu Hitung Cepat
                        </div>
                        Punya total biaya borongan/global? Masukkan dan sistem akan membaginya rata sesuai jumlah pesanan.
                    </div>
                    <div class="w-full sm:w-72 shrink-0 flex flex-col gap-2">
                        <x-currency-input wire:model="global_cost" placeholder="Ketik Total Biaya Global..." class="!bg-white dark:!bg-zinc-900" />
                        <flux:button size="sm" variant="subtle" wire:click="distributeGlobalCost" icon="arrows-right-left" class="w-full justify-center shadow-sm bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">Terapkan Pembagian</flux:button>
                    </div>
                </div>
            </div>

            <div class="mt-4" x-data="{
                get isRichText() {
                    const val = $wire.notes || '';
                    return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img') || val.includes('<table') || val.includes('<ul') || val.includes('<ol');
                }
            }">
                <div class="flex items-center justify-between mb-2">
                    <flux:label>Catatan Jasa (Global)</flux:label>
                    <flux:button size="xs" variant="subtle" icon="arrows-pointing-out" wire:click="openEditor('global')" class="!px-2 h-7" title="Buka Editor Lengkap">Editor Lengkap</flux:button>
                </div>
                
                <!-- Jika terdeteksi HTML -->
                <div x-show="isRichText" x-cloak class="relative group" wire:click="openEditor('global')">
                    <div class="w-full min-h-[5rem] max-h-[12rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 bg-white dark:bg-zinc-900/50 text-sm prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200 prose-img:rounded-xl cursor-pointer" x-html="$wire.notes">
                    </div>
                </div>
                
                <!-- Quick Note -->
                <div x-show="!isRichText">
                    <flux:textarea wire:model="notes" placeholder="Tulis instruksi atau catatan khusus vendor..." rows="3" />
                </div>
            </div>
        </div>

                    </div>
                </div> <!-- End p-6 overflow-y-auto -->

                <!-- STICKY FOOTER -->
                <div class="p-4 bg-zinc-50 dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-800 shrink-0 flex justify-end gap-3 rounded-b-xl z-20">
                    <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
                    <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Simpan & Buat Perintah Kerja</span>
                        <span wire:loading wire:target="save">Memproses...</span>
                    </flux:button>
                </div>
            </div> <!-- END PANEL 1 -->

<!-- PANEL 2: KONTEN RICH EDITOR -->
<div x-show="showEditor" style="display: none;" class="absolute inset-0 flex flex-col w-full h-full bg-slate-50 dark:bg-zinc-900/50 border-l border-zinc-200 dark:border-zinc-800 z-10">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900 shrink-0">
        <div>
            <h3 class="font-semibold text-lg text-zinc-800 dark:text-zinc-100 uppercase tracking-widest">EDITOR CATATAN</h3>
            <p class="text-[10px] text-zinc-400 tracking-wider uppercase mt-0.5">RICH TEXT MODE</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="$dispatch('hide-maklon-editor')" type="button" class="text-zinc-400 hover:text-rose-500 transition-colors p-2 rounded-full hover:bg-rose-50 dark:hover:bg-rose-900/20">
                <flux:icon.x-mark class="w-5 h-5" />
            </button>
        </div>
    </div>

    <div class="flex-1 relative p-2 sm:p-4 w-full max-w-full flex flex-col" wire:ignore>
        <div class="flex-1 w-full max-w-full border border-zinc-200 dark:border-zinc-700 rounded-lg bg-white dark:bg-zinc-900 shadow-sm flex flex-col">
            <x-rich-editor wire:model="tempNoteContent" height="100%" />
        </div>
    </div>

    <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex justify-end gap-2 shrink-0">
        <flux:button variant="ghost" wire:click="$dispatch('hide-maklon-editor')"> Batal </flux:button>
        <flux:button icon="check" variant="primary" wire:click="saveEditor"> Simpan C </flux:button>
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
    .tox-tinymce { 
        height: 100% !important; 
        width: 100% !important; 
        max-width: 100% !important;
        border: none !important; 
    }
    
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
