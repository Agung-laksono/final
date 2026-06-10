<?php

use function Livewire\Volt\{state, layout, on, updated, with};
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemLabel;
use Modules\Inventory\Models\StockAdjustment;
use Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Flux\Flux;

layout('layouts::app', ['title' => 'Stock Opname']);

state([
    'warehouses'       => fn () => Warehouse::orderBy('name')->get(),
    'warehouse_id'     => '',
    'opnameData'       => [],
    'extraItemIds'     => [], // IDs barang yang ditambahkan manual oleh petugas
    'newItemId'        => '',
    'historyAdjustments' => [],
    'historyMovements'   => [],
    'activeTab'        => 'opname',
    'selectedDocument' => null,
    'documentDetail'   => [],
    'inputMode'        => 'manual', // 'manual' atau 'barcode'
    'scanned_labels'   => [], // Menyimpan SN yang berhasil discan: [item_id => ['SN1', 'SN2']]
    'scanned_barcode'  => '', // Input dummy untuk kamera scanner
    'activeSnItemId'   => null,
    'availableLabels'  => [],
    'search'           => '',
]);

// Hitung items & availableItems secara fresh setiap render.
// Sekaligus inisialisasi opnameData secara lazy (hanya jika kunci belum ada).
with(function () {
    if (!$this->warehouse_id) {
        return ['items' => collect(), 'availableItems' => collect()];
    }

    // 1. Ambil barang dari gudang yang dipilih (beserta pivot stock)
    $warehouseItems = Warehouse::with(['items' => fn ($q) => $q->orderBy('name')])
        ->find($this->warehouse_id)
        ?->items ?? collect();

    // 2. Lazy-init opnameData — hanya jika kunci belum ada (agar input user tidak ter-reset)
    foreach ($warehouseItems as $item) {
        if (!array_key_exists($item->id, $this->opnameData)) {
            $systemStock = $item->requires_label
                ? ItemLabel::where('item_id', $item->id)
                    ->where('warehouse_id', $this->warehouse_id)
                    ->where('status', 'in_stock')
                    ->count()
                : $item->pivot->stock;

            $this->opnameData[$item->id] = [
                'system_stock' => $systemStock,
                'actual_stock' => $systemStock,
                'reason'       => '',
                'notes'        => '',
            ];
        }
    }

    // 3. Ambil barang yang ditambahkan manual (extraItemIds)
    $extraItems = count($this->extraItemIds) > 0
        ? Item::whereIn('id', $this->extraItemIds)->get()
        : collect();

    // 4. Gabungkan semua barang
    $allItems = $warehouseItems->concat($extraItems);

    // 5. Barang yang BELUM ada di opname ini = tersedia untuk ditambahkan
    $availableItems = Item::whereNotIn('id', $allItems->pluck('id'))
        ->orderBy('name')
        ->get();

    // 6. Filter by search (HANYA untuk tabel)
    if ($this->search) {
        $search = strtolower($this->search);
        $tableItems = $allItems->filter(function ($item) use ($search) {
            return str_contains(strtolower($item->name), $search) || 
                   str_contains(strtolower($item->code), $search);
        });
    } else {
        $tableItems = $allItems;
    }

    return ['items' => $tableItems, 'availableItems' => $availableItems];
});

updated([
    // Saat gudang berganti: reset semua data form
    'warehouse_id' => function () {
        $this->opnameData   = [];
        $this->extraItemIds = [];
        $this->newItemId    = '';
        $this->scanned_labels = [];
    },

    // Saat barang baru dipilih dari dropdown: tambahkan ke sesi opname
    'newItemId' => function ($value) {
        if ($value) {
            $item = Item::find($value);
            if ($item && !array_key_exists($item->id, $this->opnameData)) {
                $this->extraItemIds[] = $item->id;
                $this->opnameData[$item->id] = [
                    'system_stock' => 0,
                    'actual_stock' => '',
                    'reason'       => 'Saldo Awal',
                    'notes'        => 'Ditambahkan saat opname',
                ];
            }
            $this->newItemId = '';
        }
    },
    
    // Saat menerima input dari barcode scanner
    'scanned_barcode' => function ($barcode) {
        $barcode = trim((string) $barcode);
        if (empty($barcode) || !$this->warehouse_id) return;
        
        // 1. Cek apakah ini label SN
        $label = ItemLabel::where('label_code', $barcode)->first();
        if ($label) {
            $item = $label->item;
            if (!$item) return;

            // Jika item belum ada di tabel opname, tambahkan
            if (!array_key_exists($item->id, $this->opnameData)) {
                $this->extraItemIds[] = $item->id;
                $this->opnameData[$item->id] = [
                    'system_stock' => 0,
                    'actual_stock' => 0,
                    'reason'       => 'Saldo Awal',
                    'notes'        => 'Ditambahkan saat opname',
                ];
            }
            
            // Inisialisasi array scanned labels untuk item ini
            if (!isset($this->scanned_labels[$item->id])) {
                $this->scanned_labels[$item->id] = [];
            }
            
            // Jika belum di-scan sebelumnya di sesi ini
            if (!in_array($barcode, $this->scanned_labels[$item->id])) {
                $this->scanned_labels[$item->id][] = $barcode;
                $this->opnameData[$item->id]['actual_stock'] = count($this->scanned_labels[$item->id]);
                Flux::toast("Label {$barcode} berhasil ditambahkan", variant: 'success');
            } else {
                Flux::toast("Label {$barcode} sudah di-scan sebelumnya", variant: 'warning');
            }
            
            $this->scanned_barcode = '';
            return;
        }

        // 2. Jika bukan label, cek apakah ini SKU/Code barang biasa
        $item = Item::where('code', $barcode)->first();
        if ($item) {
            if ($item->requires_label) {
                Flux::toast("Barang {$item->name} membutuhkan scan Serial Number, bukan scan kode barang", variant: 'danger');
                $this->scanned_barcode = '';
                return;
            }

            if (!array_key_exists($item->id, $this->opnameData)) {
                $this->extraItemIds[] = $item->id;
                $this->opnameData[$item->id] = [
                    'system_stock' => 0,
                    'actual_stock' => 1,
                    'reason'       => 'Saldo Awal',
                    'notes'        => 'Ditambahkan saat opname',
                ];
                Flux::toast("Barang {$item->name} berhasil ditambahkan", variant: 'success');
            } else {
                // Tambah actual stock +1
                $current = (int) ($this->opnameData[$item->id]['actual_stock'] ?: 0);
                $this->opnameData[$item->id]['actual_stock'] = $current + 1;
                Flux::toast("Kuantitas {$item->name} +1", variant: 'success');
            }
        } else {
            Flux::toast("Barcode tidak dikenali", variant: 'danger');
        }
        
        $this->scanned_barcode = '';
    }
]);

$openSnModal = function ($itemId) {
    $this->activeSnItemId = $itemId;
    
    if (!isset($this->scanned_labels[$itemId])) {
        $this->scanned_labels[$itemId] = [];
    }
    
    $warehouseLabels = ItemLabel::where('item_id', $itemId)
        ->where('warehouse_id', $this->warehouse_id)
        ->orderBy('label_code')
        ->pluck('label_code')
        ->toArray();
        
    $this->availableLabels = array_unique(array_merge($warehouseLabels, $this->scanned_labels[$itemId]));
    
    $this->dispatch('open-sn-modal');
};

$toggleSn = function ($labelCode) {
    $itemId = $this->activeSnItemId;
    if (!$itemId) return;
    
    $scanned = $this->scanned_labels[$itemId] ?? [];
    
    if (in_array($labelCode, $scanned)) {
        $this->scanned_labels[$itemId] = array_values(array_diff($scanned, [$labelCode]));
    } else {
        $this->scanned_labels[$itemId][] = $labelCode;
    }
    
    $this->opnameData[$itemId]['actual_stock'] = count($this->scanned_labels[$itemId]);
};

$loadHistory = function () {
    $allAdjustments = StockAdjustment::with(['item', 'warehouse', 'user'])
        ->when($this->warehouse_id, fn ($q) => $q->where('warehouse_id', $this->warehouse_id))
        ->latest('created_at')
        ->take(200)
        ->get();

    $this->historyAdjustments = $allAdjustments
        ->groupBy('reference_number')
        ->map(fn ($group) => [
            'reference_number' => $group->first()->reference_number,
            'adjustment_date'  => $group->first()->adjustment_date,
            'warehouse'        => $group->first()->warehouse?->name ?? '-',
            'petugas'          => $group->first()->user?->name ?? 'Sistem',
            'total_items'      => $group->count(),
            'total_selisih'    => $group->sum('difference'),
        ])
        ->values()
        ->take(50)
        ->toArray();

    $this->selectedDocument = null;
    $this->documentDetail   = [];

    $this->historyMovements = StockMovement::with(['item', 'user'])
        ->when($this->warehouse_id, fn ($q) => $q->where('warehouse_id', $this->warehouse_id))
        ->latest('created_at')
        ->take(50)
        ->get();

    $this->dispatch('open-history-modal');
};

$loadDocumentDetail = function (string $referenceNumber) {
    $this->selectedDocument = $referenceNumber;
    $this->documentDetail   = StockAdjustment::with(['item'])
        ->where('reference_number', $referenceNumber)
        ->get()
        ->toArray();
    $this->dispatch('open-document-detail-modal');
};

$setTab = function ($tab) {
    $this->activeTab = $tab;
};

// Tarik semua barang yang tersedia (bukan dari gudang ini) ke dalam sesi opname sebagai Saldo Awal
$loadAllAvailableItems = function () {
    $warehouseItemIds = Warehouse::with('items')
        ->find($this->warehouse_id)
        ?->items->pluck('id')->toArray() ?? [];

    $allIds = array_merge($warehouseItemIds, $this->extraItemIds);

    $allAvailable = Item::whereNotIn('id', $allIds)->get();

    foreach ($allAvailable as $item) {
        $this->extraItemIds[]          = $item->id;
        $this->opnameData[$item->id]   = [
            'system_stock' => 0,
            'actual_stock' => '',
            'reason'       => 'Saldo Awal',
            'notes'        => 'Ditambahkan saat opname',
        ];
    }
};

$save = function () {
    $this->validate([
        'warehouse_id' => 'required|exists:warehouses,id',
    ]);

    $hasAdjustments = false;

    DB::beginTransaction();
    try {
        $lastRef    = StockAdjustment::where('reference_number', 'like', 'ADJ-%')
            ->orderByDesc('id')
            ->value('reference_number');
        $lastNumber      = $lastRef ? (int) substr($lastRef, 4) : 0;
        $referenceNumber = 'ADJ-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        foreach ($this->opnameData as $itemId => $data) {
            $actual     = $data['actual_stock'] !== '' ? (int) $data['actual_stock'] : $data['system_stock'];
            $system     = (int) $data['system_stock'];
            $difference = $actual - $system;

            if ($difference !== 0) {
                if (empty($data['reason'])) {
                    $itemName = Item::find($itemId)?->name ?? 'ID ' . $itemId;
                    $this->addError("opnameData.{$itemId}.reason", "Alasan wajib diisi untuk barang {$itemName}.");
                    DB::rollBack();
                    return;
                }

                $hasAdjustments = true;

                $itemModel = Item::find($itemId);
                if ($itemModel && $itemModel->requires_label) {
                    $scanned = $this->scanned_labels[$itemId] ?? [];
                    
                    // 1. Label Hilang (Ada di DB tapi tidak di-scan)
                    ItemLabel::where('item_id', $itemId)
                        ->where('warehouse_id', $this->warehouse_id)
                        ->where('status', 'in_stock')
                        ->whereNotIn('label_code', $scanned)
                        ->update(['status' => 'lost']);

                    // 2. Label Ditemukan (Di-scan, update status jadi in_stock di gudang ini)
                    if (count($scanned) > 0) {
                        ItemLabel::whereIn('label_code', $scanned)
                            ->update([
                                'status' => 'in_stock',
                                'warehouse_id' => $this->warehouse_id
                            ]);
                    }
                }

                StockAdjustment::create([
                    'reference_number' => $referenceNumber,
                    'warehouse_id'     => $this->warehouse_id,
                    'item_id'          => $itemId,
                    'system_stock'     => $system,
                    'actual_stock'     => $actual,
                    'difference'       => $difference,
                    'reason'           => $data['reason'],
                    'adjustment_date'  => now()->toDateString(),
                    'notes'            => $data['notes'],
                    'user_id'          => auth()->id(),
                ]);
            }
        }

        DB::commit();

        if ($hasAdjustments) {
            Flux::toast('Penyesuaian stok berhasil disimpan.', variant: 'success');
            
            // Beritahu user lain secara realtime via Reverb
            \App\Events\InventoryUpdated::safeDispatch("Stock Opname ({$referenceNumber}) berhasil disimpan");
            
            // Reset sesi opname
            $this->warehouse_id   = '';
            $this->opnameData     = [];
            $this->extraItemIds   = [];
        } else {
            Flux::toast('Tidak ada selisih stok yang perlu disimpan.', variant: 'info');
        }

    } catch (\Exception $e) {
        DB::rollBack();
        Flux::toast('Terjadi kesalahan: ' . $e->getMessage(), variant: 'danger');
    }
};

?>

<div @barcode-scanned.window="$wire.set('scanned_barcode', $event.detail.code)">
    {{-- Smart Sticky Header --}}
    <x-sticky-header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div class="hidden sm:block" >
            <flux:heading size="lg">Stock Opname</flux:heading>
            <flux:subheading>Sesuaikan stok fisik gudang dengan pencatatan sistem.</flux:subheading>
        </div>
        <div class="flex flex-row items-center gap-2 w-full sm:w-auto mt-3 sm:mt-0">
            <div class="flex-1">
                <flux:select wire:model.live="warehouse_id" placeholder="Pilih Gudang..." class="w-full sm:w-64">
                    <flux:select.option value="">-- Pilih Gudang --</flux:select.option>
                    @foreach ($warehouses as $w)
                        <flux:select.option value="{{ $w->id }}">{{ $w->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            
            <div class="flex gap-2 shrink-0">
                <flux:button wire:click="loadHistory" variant="outline" icon="clock" class="px-3" tooltip="Riwayat">
                    <span class="hidden sm:inline">Riwayat</span>
                </flux:button>
                @can('inventory.opname.create')
                <flux:button wire:click="save" variant="primary" icon="document-check" class="px-3" tooltip="Simpan">
                    <span class="hidden sm:inline">Simpan</span>
                </flux:button>
                @endcan
            </div>
        </div>
    </x-sticky-header>

    @if ($warehouse_id)
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden mb-6">
                <div class="p-3 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/50 flex flex-wrap items-center gap-3">
                    
                    {{-- Judul --}}
                    <div class="flex items-center gap-2 mr-auto">
                        <div class="p-1.5 bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 rounded shrink-0">
                            <flux:icon.clipboard-document-check class="w-4 h-4" />
                        </div>
                        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm whitespace-nowrap">Lembar Kerja</h3>
                    </div>
                    
                    {{-- Toggle Mode --}}
                    <flux:radio.group wire:model.live="inputMode" variant="segmented" class="flex w-auto order-1 xl:order-none shrink-0" size="sm">
                        <flux:radio value="manual" label="Manual" class="px-2" />
                        <flux:radio value="barcode" label="Barcode" class="px-2" />
                    </flux:radio.group>

                    <div class="h-5 w-px bg-zinc-300 dark:bg-zinc-700 hidden xl:block"></div>
                    
                    {{-- Search Tabel --}}
                    <div class="w-full sm:flex-1 xl:w-48 xl:flex-none order-2 xl:order-none mt-1 sm:mt-0">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari di tabel..." class="w-full" />
                    </div>

                    <div class="h-5 w-px bg-zinc-300 dark:bg-zinc-700 hidden xl:block"></div>

                    {{-- Alat --}}
                    <div class="w-full sm:flex-1 xl:w-auto xl:flex-none order-3 xl:order-none mt-1 sm:mt-0">
                        @if($inputMode === 'manual')
                            <div class="flex gap-2 w-full">
                                <div class="flex-1">
                                    <flux:select wire:model.live="newItemId" placeholder="Tambah Manual..." searchable>
                                        <flux:select.option value="">Pilih barang...</flux:select.option>
                                        @foreach($availableItems as $ai)
                                            <flux:select.option value="{{ $ai->id }}">{{ $ai->code }} - {{ $ai->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                                @if(count($availableItems) > 0)
                                    <flux:button wire:click="loadAllAvailableItems" variant="outline" icon="arrow-down-tray" class="px-2 shrink-0" tooltip="Tarik Semua Barang" />
                                @endif
                            </div>
                        @elseif($inputMode === 'barcode')
                            <div class="flex gap-2 w-full">
                                <div class="flex-1 relative">
                                    <flux:input type="text" wire:model.live.debounce.500ms="scanned_barcode" placeholder="Pindai alat..." icon="qr-code" autofocus class="w-full bg-indigo-50 dark:bg-indigo-500/10 border-indigo-200 dark:border-indigo-500/30 text-indigo-900 dark:text-indigo-100" />
                                </div>
                                <flux:button type="button" x-on:click="Flux.modal('camera-scanner-modal').show(); window.dispatchEvent(new Event('camera-scanner-modal-opened'))" variant="filled" class="bg-indigo-600 hover:bg-indigo-700 text-white border-none px-2 shrink-0" icon="camera" tooltip="Gunakan Kamera HP" />
                            </div>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto bg-zinc-50 dark:bg-zinc-800/20 md:bg-transparent p-3 md:p-0">
                    <flux:table class="block md:table ml-3">
                        <flux:table.columns class="hidden md:table-header-group">
                            <flux:table.column>Barang</flux:table.column>
                            <flux:table.column class="text-center w-24">Sistem</flux:table.column>
                            <flux:table.column class="text-center w-32">Aktual</flux:table.column>
                            <flux:table.column class="text-center w-24">Selisih</flux:table.column>
                            <flux:table.column class="w-48">Alasan</flux:table.column>
                            <flux:table.column>Catatan</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows class="block md:table-row-group space-y-3 md:space-y-0 max-md:[&>tr>td]:!border-t-0 max-md:[&>tr>td]:!px-0">
                            @foreach ($items as $item)
                                @php
                                    $sysStock = $opnameData[$item->id]['system_stock'] ?? 0;
                                    $xData = "{ 
                                        sys: {$sysStock},
                                        act: \$wire.entangle('opnameData.{$item->id}.actual_stock').live,
                                        get diff() {
                                            if (this.act === '') return 0;
                                            return (parseInt(this.act) || 0) - this.sys;
                                        }
                                    }";
                                @endphp
                                <flux:table.row wire:key="opname-row-{{ $item->id }}-{{ $warehouse_id }}" x-data="{{ $xData }}" class="block md:table-row bg-white dark:bg-zinc-900 md:bg-transparent rounded-xl md:rounded-none shadow-sm md:shadow-none border border-zinc-200 dark:border-zinc-800 md:border-none p-4 md:p-0 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    {{-- Barang --}}
                                    <flux:table.cell class="block md:table-cell border-b border-dashed border-zinc-200 dark:border-zinc-700 md:border-b-0 pb-3 md:pb-3 mb-2 md:mb-0">
                                        <div class="flex flex-col w-full">
                                            <span class="font-medium text-base md:text-sm text-zinc-900 dark:text-zinc-100">{{ $item->name }}</span>
                                            <span class="text-xs md:text-[10px] font-mono text-zinc-500">{{ $item->code }}</span>
                                        </div>
                                    </flux:table.cell>
                                    
                                    {{-- Sistem --}}
                                    <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                        <div class="flex w-full items-center justify-between md:justify-center">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Sistem</span>
                                            <div class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 bg-zinc-100 dark:bg-zinc-800 rounded font-semibold text-zinc-700 dark:text-zinc-300">
                                                {{ $opnameData[$item->id]['system_stock'] ?? 0 }}
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    {{-- Aktual --}}
                                    <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                        <div class="flex w-full items-center justify-between md:justify-center gap-4">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium shrink-0">Aktual</span>
                                            <div class="flex flex-col items-end md:items-center w-32 md:w-full gap-1">
                                                @if($item->requires_label)
                                                    <flux:input type="number" value="{{ count($scanned_labels[$item->id] ?? []) }}" class="w-full text-right md:text-center h-8 text-sm" disabled />
                                                    
                                                    @if($inputMode === 'manual')
                                                        <flux:button wire:click="openSnModal({{ $item->id }})" size="xs" variant="outline" class="w-full text-[10px] h-6 px-1">
                                                            Pilih SN
                                                        </flux:button>
                                                    @else
                                                        <p class="text-[10px] text-zinc-500 text-right md:text-center w-full">Scan label SN</p>
                                                    @endif
                                                @else
                                                    <flux:input type="number" wire:model.live="opnameData.{{ $item->id }}.actual_stock" placeholder="0" class="w-full text-right md:text-center h-8 text-sm" :disabled="$inputMode === 'barcode'" />
                                                    
                                                    @if($inputMode === 'barcode')
                                                        <p class="text-[10px] text-zinc-500 text-right md:text-center w-full">Scan SKU</p>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    {{-- Selisih --}}
                                    <flux:table.cell class="block md:table-cell py-1.5 md:py-3 border-b border-dashed border-zinc-200 dark:border-zinc-700 md:border-b-0 pb-3 md:pb-3 mb-2 md:mb-0">
                                        <div class="flex w-full items-center justify-between md:justify-center">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Selisih</span>
                                            <span x-text="diff > 0 ? '+' + diff : diff" 
                                                  :class="{
                                                    'text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-500/10 px-2 py-1 rounded text-sm': diff > 0,
                                                    'text-rose-600 dark:text-rose-400 font-bold bg-rose-50 dark:bg-rose-500/10 px-2 py-1 rounded text-sm': diff < 0,
                                                    'text-zinc-400 font-bold text-sm': diff === 0
                                                  }">
                                            </span>
                                        </div>
                                    </flux:table.cell>
                                    
                                    {{-- Alasan --}}
                                    <flux:table.cell :class="diff !== 0 ? 'block' : 'hidden'" class="md:table-cell py-1.5 md:py-3">
                                        <div x-show="diff !== 0" x-transition class="flex w-full items-center justify-between md:block gap-4">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium shrink-0">Alasan</span>
                                            <div class="w-48 md:w-full">
                                                <flux:select wire:model="opnameData.{{ $item->id }}.reason" placeholder="Pilih alasan..." class="w-full h-8 text-sm">
                                                    <flux:select.option value="Salah Hitung">Salah Hitung</flux:select.option>
                                                    <flux:select.option value="Barang Ditemukan">Barang Ditemukan (+)</flux:select.option>
                                                    <flux:select.option value="Kelebihan Terima">Kelebihan Terima (+)</flux:select.option>
                                                    <flux:select.option value="Hilang">Hilang (-)</flux:select.option>
                                                    <flux:select.option value="Rusak">Rusak (-)</flux:select.option>
                                                    <flux:select.option value="Kadaluarsa">Kadaluarsa (-)</flux:select.option>
                                                    <flux:select.option value="Lainnya">Lainnya...</flux:select.option>
                                                </flux:select>
                                                @error('opnameData.'.$item->id.'.reason')
                                                    <div class="text-xs text-red-500 mt-1 text-right md:text-left">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    
                                    {{-- Catatan --}}
                                    <flux:table.cell :class="diff !== 0 ? 'block' : 'hidden'" class="md:table-cell py-1.5 md:py-3 pb-0 md:pb-3">
                                        <div x-show="diff !== 0" x-transition class="flex w-full items-center justify-between md:block gap-4">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium shrink-0">Catatan</span>
                                            <div class="w-48 md:w-full">
                                                <flux:input wire:model="opnameData.{{ $item->id }}.notes" placeholder="Opsional..." class="w-full h-8 text-sm" />
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                    
                    @if(count($items) === 0)
                    <div class="flex flex-col items-center justify-center py-16">
                        <div class="p-4 bg-zinc-50 dark:bg-zinc-800 rounded-full mb-4">
                            <flux:icon.cube class="w-8 h-8 text-zinc-400" />
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Gudang Ini Kosong</h3>
                        <p class="text-sm text-zinc-500 text-center max-w-sm mb-4">Belum ada barang di gudang ini. Silakan gunakan kotak pencarian di atas untuk memasukkan barang sebagai Saldo Awal.</p>
                    </div>
                    @endif
                </div>
            </div>
    @else
        <div class="flex flex-col items-center justify-center py-24 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 border-dashed">
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-full mb-4 ring-8 ring-blue-50/50 dark:ring-blue-900/10">
                <flux:icon.building-storefront class="w-10 h-10 text-blue-500" />
            </div>
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Pilih Gudang</h3>
            <p class="text-sm text-zinc-500">Silakan pilih gudang dari dropdown di atas untuk memulai sesi Stock Opname.</p>
        </div>
    @endif

    {{-- History Modals --}}
    @include('inventory::livewire.item-opname.history-modal')

    {{-- SN Selection Modal --}}
    @include('inventory::livewire.item-opname.sn-modal')

    <!-- Komponen Global Scanner Kamera -->
    <x-camera-scanner />
</div>
