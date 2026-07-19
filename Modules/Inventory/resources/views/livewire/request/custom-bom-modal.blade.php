<?php
use Livewire\Volt\Component;
use Modules\Inventory\Models\InventoryRequest;
use Modules\Production\Models\ProductionRecipe;
use Modules\Production\Models\ProductionOrder;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;
use Livewire\Attributes\On;

new class extends Component {
    public $show = false;
    public $requestId = null;
    public $request = null;
    public $customOrderItem = null;
    
    // BOM items
    public $items = [];
    public $searchItem = '';
    public $availableItems = [];
    public $woQty = 1;
    public $notes = '';

    #[On('open-custom-bom-modal')]
    public function loadRequest($requestId, $qty = 1)
    {
        $this->reset(['items', 'customOrderItem', 'notes', 'searchItem', 'availableItems']);
        $this->requestId = $requestId;
        $this->woQty = $qty;
        $this->request = InventoryRequest::with(['item'])->find($requestId);
        
        if ($this->request) {
            // Coba cari referensi Custom Specs
            if (str_starts_with($this->request->reference_number, 'ODM-') || str_starts_with($this->request->reference_number, 'PEM-')) {
                // Bisa dari SO atau PO, kita cek SO dulu
                $so = SalesOrder::where('so_number', $this->request->reference_number)->first();
                if ($so) {
                    $this->customOrderItem = SalesOrderItem::where('sales_order_id', $so->id)
                        ->where('item_id', $this->request->item_id)
                        ->where(function($q) {
                            $q->whereNotNull('custom_attributes')->orWhereNotNull('custom_attachments');
                        })
                        ->first();
                } else {
                    $po = \Modules\Purchase\Models\PurchaseOrder::where('po_number', $this->request->reference_number)->first();
                    if ($po) {
                        $this->customOrderItem = \Modules\Purchase\Models\PurchaseOrderItem::where('purchase_order_id', $po->id)
                            ->where('item_id', $this->request->item_id)
                            ->where(function($q) {
                                $q->whereNotNull('custom_attributes')->orWhereNotNull('custom_attachments');
                            })
                            ->first();
                    }
                }
            }

            // Muat resep default
            $recipe = ProductionRecipe::with('items.item')->where('item_id', $this->request->item_id)->where('is_active', true)->first();
            if ($recipe) {
                foreach ($recipe->items as $ri) {
                    $this->items[] = [
                        'item_id' => $ri->item_id,
                        'name' => $ri->item->name,
                        'qty' => $ri->qty, // Qty per 1 unit product
                        'unit' => $ri->item->unit->name ?? 'pcs',
                        'image' => $ri->item->image ?? null,
                    ];
                }
            }
        }
        $this->show = true;
    }

    #[On('customizer-saved')]
    public function handleCustomizerSaved($data)
    {
        $index = $data['index'];
        if (isset($this->items[$index])) {
            $this->items[$index]['note'] = $data['note'] ?? '';
            $this->items[$index]['custom_attributes'] = $data['custom_attributes'] ?? [];
            $this->items[$index]['custom_attachments'] = $data['custom_attachments'] ?? [];
        }
    }

    public function updatedSearchItem()
    {
        if (strlen($this->searchItem) > 1) {
            $this->availableItems = \Modules\Inventory\Models\Item::where('name', 'like', '%' . $this->searchItem . '%')
                ->orWhere('code', 'like', '%' . $this->searchItem . '%')
                ->take(5)->with('unit')->get()->toArray();
        } else {
            $this->availableItems = [];
        }
    }

    public function addItem($itemId, $itemName, $unitName = 'pcs', $image = null)
    {
        // Cek duplicate
        foreach ($this->items as $i) {
            if ($i['item_id'] == $itemId) {
                \Flux::toast('Barang sudah ada di resep.', variant: 'warning');
                $this->searchItem = '';
                $this->availableItems = [];
                return;
            }
        }

        $this->items[] = [
            'item_id' => $itemId,
            'name' => $itemName,
            'qty' => 1,
            'unit' => $unitName ?? 'pcs',
            'image' => $image,
        ];
        $this->searchItem = '';
        $this->availableItems = [];
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function saveAndRoute()
    {
        abort_unless(auth()->user()->can('inventory.request.update'), 403);
        
        $req = InventoryRequest::with('item')->find($this->requestId);
        if (!$req || $req->status === 'routed') return;

        if (empty($this->items)) {
            \Flux::toast('Resep bahan baku tidak boleh kosong!', variant: 'danger');
            return;
        }

        // Generate sequential PROD-0001 format
        $latestProd = ProductionOrder::orderBy('id', 'desc')->first();
        $nextId = $latestProd ? $latestProd->id + 1 : 1000;
        $orderNumber = 'PROD-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $hasDeficit = false;

        // --- CUSTOM BOM EXPLOSION ---
        foreach ($this->items as $ri) {
            $needed = $ri['qty'] * $this->woQty;
            $stock = \Illuminate\Support\Facades\DB::table('item_warehouse')
                ->where('item_id', $ri['item_id'])
                ->sum('stock') ?? 0;
            $allocated = \Illuminate\Support\Facades\DB::table('item_warehouse')
                ->where('item_id', $ri['item_id'])
                ->sum('allocated_qty') ?? 0;
                
            $atp = $stock - $allocated;
            $deficit = max(0, $needed - $atp);
            
            // Reservasi stok (alokasi)
            $updated = \Illuminate\Support\Facades\DB::table('item_warehouse')
                ->where('item_id', $ri['item_id'])
                ->orderBy('stock', 'desc')
                ->limit(1)
                ->update(['allocated_qty' => \Illuminate\Support\Facades\DB::raw('allocated_qty + ' . $needed)]);

            if (!$updated) {
                \Illuminate\Support\Facades\DB::table('item_warehouse')->insert([
                    'item_id' => $ri['item_id'],
                    'warehouse_id' => 1, // Default warehouse
                    'stock' => 0,
                    'allocated_qty' => $needed,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            if ($deficit > 0) {
                $hasDeficit = true;
                // Auto-create material request
                $invReq = InventoryRequest::create([
                    'item_id' => $ri['item_id'],
                    'source_type' => 'production',
                    'reference_number' => $orderNumber,
                    'requested_qty' => $deficit,
                    'notes' => "[CUSTOM] Auto-Generated: Defisit bahan baku untuk {$orderNumber} (Produksi Custom {$req->item->name}). Butuh: {$needed}, ATP: {$atp} (Fisik: {$stock}, Dipesan: {$allocated})." . (!empty($ri['note']) ? "\n\nCatatan Khusus: " . $ri['note'] : ""),
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                    'custom_attributes' => $ri['custom_attributes'] ?? null,
                    'custom_attachments' => $ri['custom_attachments'] ?? null,
                ]);
            }
        }
        
        // Simpan ke Production Order dengan kolom custom_bom terisi
        ProductionOrder::create([
            'order_number' => $orderNumber,
            'item_id' => $req->item_id,
            'requested_qty' => $this->woQty,
            'reference_number' => $req->reference_number,
            'notes' => 'Dialihkan dari Pivot Gudang (CUSTOM BOM). Notes: ' . $this->notes . ($req->notes ? " | Ref: " . $req->notes : ""),
            'status' => $hasDeficit ? 'waiting_material' : 'material_fulfillment',
            'created_by' => auth()->id(),
            'custom_bom' => json_encode($this->items), // SIMPAN CUSTOM BOM
        ]);
        
        $req->status = 'routed';
        $req->routed_to = 'production';
        $req->routed_by = auth()->id();
        $req->save();
        
        \App\Events\KanbanUpdated::safeDispatch('inventory_request');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        
        $this->show = false;

        if ($hasDeficit) {
            \Flux::toast('Dialihkan ke Produksi. PERINGATAN: Bahan baku kurang, tiket pembelian diterbitkan otomatis!', variant: 'warning');
        } else {
            \Flux::toast('Berhasil dialihkan ke Antrean Produksi dengan Resep Kustom. Bahan baku terpantau aman.', variant: 'success');
        }
    }
};
?>

<div>
<div x-on:item-selected.window="if ($wire.show) { $wire.addItem($event.detail.item.item_id, $event.detail.item.name, $event.detail.item.unit || 'pcs', $event.detail.item.image || null); }">
<flux:modal wire:model="show" class="w-full md:w-[65rem] md:max-w-5xl">
    @if($request)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4 border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-5">
            <div class="flex-shrink-0 w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center border border-amber-200">
                <flux:icon.sparkles class="w-6 h-6" />
            </div>
            <div class="flex-1">
                <flux:heading size="xl">Custom BOM Tweaker</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500">
                    Sesuaikan resep produksi untuk pesanan khusus <strong>{{ $request->reference_number }}</strong> ({{ $request->item->name }}).
                </flux:subheading>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-6">
            {{-- Bagian Kiri: Info Custom --}}
            <div class="w-full md:w-1/3 flex flex-col gap-4">
                @if($customOrderItem)
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 rounded-xl p-4 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-4 opacity-10">
                            <flux:icon.star class="w-24 h-24 text-amber-500" />
                        </div>
                        <div class="relative z-10">
                            <flux:heading size="md" class="!text-amber-800 dark:!text-amber-400 mb-3 flex items-center gap-2">
                                <flux:icon.document-text class="w-5 h-5" />
                                Spesifikasi Pelanggan
                            </flux:heading>
                            
                            @if(!empty($customOrderItem->custom_attributes))
                                <div class="space-y-2 mb-4">
                                    @foreach($customOrderItem->custom_attributes as $attr)
                                        <div class="bg-white/60 dark:bg-zinc-900/40 rounded-lg p-2.5 flex justify-between items-start text-sm border border-amber-100 dark:border-amber-800/30">
                                            <span class="font-medium text-amber-900 dark:text-amber-200">{{ $attr['key'] }}</span>
                                            <span class="text-amber-700 dark:text-amber-400 text-right">{{ $attr['value'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($customOrderItem->custom_attachments))
                                <div>
                                    <h4 class="text-xs font-bold text-amber-700 dark:text-amber-500 uppercase tracking-wider mb-2">Lampiran Gambar</h4>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach($customOrderItem->custom_attachments as $img)
                                            <a href="{{ asset('storage/' . $img) }}" target="_blank" class="block rounded-lg overflow-hidden border border-amber-200 shadow-sm hover:opacity-80 transition-opacity">
                                                <img src="{{ asset('storage/' . $img) }}" class="w-full h-24 object-cover" />
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            
                            @if(empty($customOrderItem->custom_attributes) && empty($customOrderItem->custom_attachments))
                                <div class="text-sm text-amber-700">Tidak ada detail atribut.</div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 text-center text-zinc-500 text-sm">
                        <flux:icon.information-circle class="w-8 h-8 mx-auto mb-2 opacity-50" />
                        Data spesifikasi pesanan custom tidak ditemukan untuk referensi ini.
                    </div>
                @endif
                
                <div class="mt-auto">
                    <flux:textarea wire:model="notes" label="Catatan Tambahan untuk Produksi" placeholder="Tulis instruksi khusus di sini..." />
                </div>
            </div>

            {{-- Bagian Kanan: BOM Editor --}}
            <div class="w-full md:w-2/3 flex flex-col h-[500px]">
                <div class="mb-3">
                    <flux:heading size="md">Bahan Baku (Resep Kustom)</flux:heading>
                    <flux:subheading class="text-xs mt-1">Ubah kuantitas atau tambahkan bahan baru untuk 1 unit produk.</flux:subheading>
                </div>
                <div class="flex items-center gap-2 mb-4">
                    <div class="relative flex-1">
                        <flux:input wire:model.live.debounce.300ms="searchItem" placeholder="Cari bahan tambahan..." icon="magnifying-glass" />
                        @if(!empty($availableItems))
                            <div class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                                @foreach($availableItems as $ai)
                                    <div wire:click="addItem({{ $ai['id'] }}, '{{ addslashes($ai['name']) }}', '{{ addslashes($ai['unit']['name'] ?? 'pcs') }}', '{{ $ai['image'] ?? '' }}')" class="px-3 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer text-sm border-b border-zinc-100 dark:border-zinc-700 last:border-0 flex items-center gap-3">
                                        @if(!empty($ai['image']))
                                            <img src="{{ Storage::url($ai['image']) }}" class="w-8 h-8 rounded bg-zinc-100 object-cover shrink-0">
                                        @else
                                            <div class="w-8 h-8 rounded bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 shrink-0">
                                                <flux:icon.cube class="w-4 h-4" />
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-bold text-zinc-800 dark:text-zinc-200">{{ $ai['name'] }}</div>
                                            <div class="text-[10px] text-zinc-500">{{ $ai['code'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <flux:button variant="primary" class="shrink-0" x-on:click="Livewire.dispatch('open-gallery', { context: 'production' }); setTimeout(() => { $flux.modal('gallery-modal').show(); }, 100)">
                        <div class="flex items-center gap-2">
                            <flux:icon.squares-2x2 class="w-4 h-4" />
                            <span class="hidden md:block">Galeri</span>
                        </div>
                    </flux:button>
                </div>

                <div class="flex-1 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-xl bg-zinc-50 dark:bg-zinc-900/50 p-2">
                    <div class="space-y-2">
                        @forelse($items as $index => $item)
                            <div wire:key="bom-item-{{ $item['item_id'] }}" class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center gap-3 shadow-sm relative group hover:border-emerald-500/30 transition-colors">
                                <div class="flex-1 flex items-center gap-3 min-w-0">
                                    @if(!empty($item['custom_attachments']))
                                        <div class="relative">
                                            <img src="{{ asset('storage/' . $item['custom_attachments'][0]) }}" class="w-10 h-10 rounded-lg object-cover bg-amber-100 ring-2 ring-amber-400 shrink-0 shadow-sm">
                                            <div class="absolute -top-1 -right-1 bg-amber-500 text-white rounded-full p-0.5 shadow-sm">
                                                <flux:icon.sparkles class="w-3 h-3" />
                                            </div>
                                        </div>
                                    @elseif(!empty($item['image']))
                                        <img src="{{ Storage::url($item['image']) }}" class="w-10 h-10 rounded-lg object-cover bg-zinc-100 shrink-0">
                                    @else
                                        <div class="w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 shrink-0">
                                            <flux:icon.cube class="w-5 h-5" />
                                        </div>
                                    @endif
                                    <div class="flex flex-col min-w-0">
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100 text-sm truncate">{{ $item['name'] }}</span>
                                        @if(!empty($item['custom_attributes']) || !empty($item['custom_attachments']))
                                            <div class="flex items-center gap-1 mt-1.5">
                                                <span class="text-[9px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200 uppercase tracking-wider font-semibold">Custom MTO</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 mt-1 sm:mt-0 justify-between sm:justify-end border-t sm:border-0 border-zinc-100 dark:border-zinc-700 pt-3 sm:pt-0">
                                    <div class="flex items-center gap-2">
                                        <div class="w-24">
                                            <flux:input type="number" wire:model.blur="items.{{ $index }}.qty" min="0.01" step="0.01" class="text-center !h-8 !text-sm" />
                                        </div>
                                        <span class="text-xs text-zinc-500 font-medium w-8">{{ $item['unit'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <flux:button variant="subtle" size="sm" icon="pencil-square" class="!bg-amber-500 hover:!bg-amber-600 !text-white w-8 h-8 p-0 shrink-0" @click="Livewire.dispatch('open-customizer', { index: {{ $index }}, itemData: { item_id: {{ $item['item_id'] }}, name: '{{ addslashes($item['name']) }}', note: '{{ addslashes($item['note'] ?? '') }}', custom_attributes: {{ json_encode($item['custom_attributes'] ?? []) }}, custom_attachments: {{ json_encode($item['custom_attachments'] ?? []) }} } })" title="Spesifikasi Kustom" />
                                        <flux:button variant="subtle" size="sm" icon="trash" wire:click="removeItem({{ $index }})" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 w-8 h-8 p-0 shrink-0" />
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-zinc-400">
                                <flux:icon.beaker class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                Resep kosong. Silakan cari dan tambahkan bahan di atas.
                            </div>
                        @endforelse
                    </div>
                </div>
                
                <div class="mt-3 text-[10px] text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-900/20 px-3 py-2 rounded-lg border border-amber-100 dark:border-amber-800/30 flex items-start gap-2">
                    <flux:icon.information-circle class="w-4 h-4 shrink-0" />
                    Perubahan pada tabel di atas HANYA akan mempengaruhi produksi untuk SPK ini saja dan tidak mengubah Master BOM. Kuantitas akan dikalikan {{ $woQty }} secara otomatis.
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
            <flux:button variant="primary" icon="check" wire:click="saveAndRoute" wire:target="saveAndRoute" wire:loading.attr="disabled">Simpan Kustomisasi & Terbitkan SPK</flux:button>
        </div>
    </div>
    @endif
</flux:modal>

    {{-- Render Customizer Modal in the same context --}}
    <livewire:global.item-customizer-modal />
</div>
