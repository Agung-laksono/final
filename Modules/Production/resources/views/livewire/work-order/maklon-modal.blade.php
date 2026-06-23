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
    
    $orders = ProductionOrder::whereIn('id', $this->orderIds)->get();
    foreach ($orders as $order) {
        $this->costs[$order->item_id] = null;
    }
    
    $this->show = true;
}]);

state([
    'editingNoteKey' => null,
]);

$saveMaklonNote = function ($content) {
    // Dipindahkan ke frontend (AlpineJS)
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
    
    $this->validate([
        'vendor_id' => 'required',
        'costs.*' => 'required|numeric|min:0'
    ], [
        'vendor_id.required' => 'Pilih vendor terlebih dahulu.',
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

        foreach ($this->groupedOrders as $itemId => $group) {
            $groupCost = $this->costs[$itemId] ?? 0;
            $groupTotalQty = max(1, $group['total_qty']);
            $costPerUnit = $groupCost / $groupTotalQty;

            // Create PO Item
            $po->items()->create([
                'item_id' => $itemId, // we use the finished good item id
                'quantity' => $groupTotalQty,
                'unit_price' => $costPerUnit, // cost per item
                'subtotal' => $groupCost,
                'notes' => "Jasa Maklon Fase: " . ucfirst($this->phase_type)
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
    });

    $this->dispatch('maklon-po-created');
    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('production_order');
    $this->show = false;
    \Flux::toast('Perintah Kerja Maklon berhasil dibuat!', variant: 'success');
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
        editorKey: null,
        _tinyLoaded: false,

        init() {
            if (this.show) document.body.classList.add('overflow-hidden');

            window.addEventListener('open-maklon-editor', (e) => {
                this.editorKey = e.detail.key;
                this.showEditor = true;
                this.initEditor(e.detail.content || '');
            });
            
            // Watch for modal close to also close editor
            this.$watch('show', value => {
                if (!value) {
                    this.showEditor = false;
                    document.body.classList.remove('overflow-hidden');
                    // Hancurkan TinyMCE saat modal utama ditutup
                    setTimeout(() => { if (window.tinymce) tinymce.remove('#maklon-inline-textarea'); }, 300);
                } else {
                    document.body.classList.add('overflow-hidden');
                }
            });

            // Hancurkan TinyMCE saat panel editor ditutup (animasi slide selesai)
            this.$watch('showEditor', value => {
                if (!value) {
                    setTimeout(() => { if (window.tinymce) tinymce.remove('#maklon-inline-textarea'); }, 500);
                }
            });
        },

        loadTinyMCE(callback) {
            if (window.tinymce) return callback();
            if (this._tinyLoaded) {
                const wait = setInterval(() => {
                    if (window.tinymce) { clearInterval(wait); callback(); }
                }, 50);
                return;
            }
            this._tinyLoaded = true;
            const script = document.createElement('script');
            script.src = '{{ asset('vendor/tinymce/tinymce.min.js') }}';
            script.onerror = () => {
                const fb = document.createElement('script');
                fb.src = 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.5.1/tinymce.min.js';
                fb.onload = callback;
                document.head.appendChild(fb);
            };
            script.onload = callback;
            document.head.appendChild(script);
        },

        initEditor(content) {
            this.loadTinyMCE(() => {
                setTimeout(() => {
                    if (tinymce.get('maklon-inline-textarea')) {
                        tinymce.get('maklon-inline-textarea').remove();
                    }
                    tinymce.init({
                        selector: '#maklon-inline-textarea',
                        license_key: 'gpl',
                        height: 400,
                        menubar: false,
                        promotion: false,
                        branding: false,
                        skin: document.documentElement.classList.contains('dark') ? 'oxide-dark' : 'oxide',
                        content_css: document.documentElement.classList.contains('dark') ? 'dark' : 'default',
                        plugins: 'lists link table code',
                        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | table | code',
                        setup: (editor) => {
                            editor.on('init', () => {
                                editor.setContent(content || '');
                                editor.focus();
                            });
                        }
                    });
                }, 100);
            });
        },

        saveEditor() {
            if (window.tinymce && tinymce.get('maklon-inline-textarea')) {
                const content = tinymce.get('maklon-inline-textarea').getContent();
                if (this.editorKey === 'global') {
                    $wire.notes = content;
                } else {
                    $wire.item_notes[this.editorKey] = content;
                }
            }
            this.showEditor = false;
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
        
        <!-- ROMVER SLIDING CONTAINER: Wraps both normal content and editor -->
        <div class="flex-grow flex flex-nowrap w-[200%] transition-transform duration-500 ease-out h-full overflow-hidden"
             :style="showEditor ? 'transform: translateX(-50%)' : 'transform: translateX(0%)'">
             
            <!-- PANEL 1: KONTEN NORMAL -->
            <div class="w-1/2 shrink-0 flex flex-col relative h-full">
                <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Buat Perintah Kerja Maklon</flux:heading>
            <flux:subheading>Kirim barang-barang ini ke vendor eksternal dan buat tagihannya.</flux:subheading>
        </div>

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
                                            <div class="relative">
                                                <flux:textarea wire:model="item_notes.group_{{ $itemId }}" placeholder="Catatan sederhana..." rows="2" />
                                                <button type="button" @click="$dispatch('open-maklon-editor', { content: $wire.item_notes['group_{{ $itemId }}'] || '', key: 'group_{{ $itemId }}' })" class="absolute right-2 top-2 p-1 text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 bg-white dark:bg-zinc-900 rounded" title="Buka Editor Lengkap">
                                                    <flux:icon.arrows-pointing-out class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-zinc-500 mt-0.5">
                                        Digabungkan dari {{ count($group['orders']) }} pesanan • Total Qty: <strong class="text-zinc-800 dark:text-zinc-200">{{ $group['total_qty'] }}</strong>
                                    </div>
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500 mt-1 cursor-pointer hover:text-indigo-600 transition-colors" x-on:click="open = true">
                                        <span class="italic line-clamp-1">— Tidak ada catatan khusus.</span>
                                    </div>
                                </div>
                                <div class="w-full sm:w-64 shrink-0 flex gap-2">
                                    <div class="relative flex-1">
                                        <x-currency-input wire:model="costs.group_{{ $itemId }}" placeholder="0" />
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
                                            <div class="relative">
                                                <flux:textarea wire:model="item_notes.single_{{ $order->id }}" placeholder="Catatan sederhana..." rows="2" />
                                                <button type="button" @click="$dispatch('open-maklon-editor', { content: $wire.item_notes['single_{{ $order->id }}'] || '', key: 'single_{{ $order->id }}' })" class="absolute right-2 top-2 p-1 text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 bg-white dark:bg-zinc-900 rounded" title="Buka Editor Lengkap">
                                                    <flux:icon.arrows-pointing-out class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-zinc-500 mt-0.5">
                                        Ref: {{ $order->production_req_number }} • Qty: <strong class="text-zinc-800 dark:text-zinc-200">{{ $order->requested_qty }}</strong>
                                    </div>
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500 mt-1 cursor-pointer hover:text-indigo-600 transition-colors" x-on:click="open = true">
                                        <span class="italic line-clamp-1">— Tidak ada catatan khusus.</span>
                                    </div>
                                </div>
                                <div class="w-full sm:w-64 shrink-0 flex gap-2">
                                    <div class="relative flex-1">
                                        <x-currency-input wire:model="costs.single_{{ $order->id }}" placeholder="0" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <flux:label>Catatan Jasa (Global)</flux:label>
                    <flux:button size="xs" variant="subtle" icon="document-text" x-on:click="$flux.modal('template-modal').show()">Gunakan Template</flux:button>
                </div>
                <div class="relative">
                    <flux:textarea wire:model="notes" placeholder="Tulis instruksi atau catatan khusus vendor..." rows="3" />
                    <button type="button" @click="$dispatch('open-maklon-editor', { content: $wire.notes || '', key: 'global' })" class="absolute right-2 top-2 p-1 text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 bg-white dark:bg-zinc-900 rounded" title="Buka Editor Lengkap">
                        <flux:icon.arrows-pointing-out class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800 mt-4">
            <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
            <flux:button variant="primary" wire:click="save">Simpan & Buat Tagihan</flux:button>
        </div>
    </div> <!-- End space-y-6 -->
</div> <!-- End p-6 -->
</div> <!-- END PANEL 1 -->

<!-- PANEL 2: KONTEN RICH EDITOR -->
<div class="w-1/2 shrink-0 flex flex-col relative h-full bg-slate-50 dark:bg-zinc-900/50 border-l border-zinc-200 dark:border-zinc-800">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900">
        <div>
            <h3 class="font-semibold text-lg text-zinc-800 dark:text-zinc-100 uppercase tracking-widest">EDITOR CATATAN</h3>
            <p class="text-[10px] text-zinc-400 tracking-wider uppercase mt-0.5">RICH TEXT MODE</p>
        </div>
        <button @click="showEditor = false" type="button" class="text-zinc-400 hover:text-rose-500 transition-colors p-2 rounded-full hover:bg-rose-50 dark:hover:bg-rose-900/20">
            <flux:icon.x-mark class="w-5 h-5" />
        </button>
    </div>

    <div class="flex-1 overflow-hidden relative bg-white dark:bg-zinc-900" wire:ignore id="tinymce-wrapper">
        <textarea id="maklon-inline-textarea" class="w-full h-full border-0"></textarea>
    </div>

    <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex justify-end gap-2 shrink-0">
        <flux:button variant="ghost" @click="showEditor = false">Batal</flux:button>
        <flux:button variant="primary" @click="saveEditor()">Simpan Catatan</flux:button>
    </div>
</div> <!-- END PANEL 2 -->

        </div> <!-- END ROMVER SLIDING CONTAINER -->
    </div> <!-- END OF MAIN MODAL CONTAINER -->

</div> <!-- END OF FIXED WRAPPER -->


<livewire:global.vendor-gallery-modal />
<livewire:work-order.price-history-modal />



<!-- Mockup Template Modal -->
<flux:modal name="template-modal" class="md:w-[500px]">
    <flux:heading size="lg" class="mb-4">Pilih Template Catatan</flux:heading>
    <div class="space-y-2">
        <button class="w-full text-left p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors" x-on:click="$wire.set('notes', 'Mohon dikerjakan sesuai standar perusahaan. Pastikan warna cat merata dan tidak belang. Tenggat waktu pengiriman sangat ketat.'); $flux.modal('template-modal').close()">
            <strong class="block text-sm">Instruksi Finishing Standar</strong>
            <span class="text-xs text-zinc-500 line-clamp-1">Mohon dikerjakan sesuai standar perusahaan...</span>
        </button>
        <button class="w-full text-left p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors" x-on:click="$wire.set('notes', 'Gunakan kain Oscar warna Coklat Tua (kode: OSC-BR-01). Busa menggunakan Yellow Super 5cm.'); $flux.modal('template-modal').close()">
            <strong class="block text-sm">Instruksi Jok Oscar Coklat</strong>
            <span class="text-xs text-zinc-500 line-clamp-1">Gunakan kain Oscar warna Coklat Tua...</span>
        </button>
    </div>
    <div class="flex justify-end mt-4">
        <flux:button variant="subtle" x-on:click="$flux.modal('template-modal').close()">Tutup</flux:button>
    </div>
</flux:modal>
@once
<style>
    /* FIX: Mencegah pop-up TinyMCE tertutup Modal/elemen lain */
    .tox-tinymce-aux {
        z-index: 999999 !important;
    }
</style>
<script>
// Prevent focus trap from Flux or Bootstrap from stealing focus away from TinyMCE dropdowns
document.addEventListener('focusin', function (e) {
    if (e.target.closest('.tox-tinymce-aux, .moxman-window, .tam-assetmanager-root') !== null) {
        e.stopImmediatePropagation();
    }
});
</script>
@endonce
</div>
