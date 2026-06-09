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

    // 4. Gabungkan semua barang untuk ditampilkan di tabel
    $allItems = $warehouseItems->concat($extraItems);

    // 5. Barang yang BELUM ada di opname ini = tersedia untuk ditambahkan
    $availableItems = Item::whereNotIn('id', $allItems->pluck('id'))
        ->orderBy('name')
        ->get();

    return ['items' => $allItems, 'availableItems' => $availableItems];
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
        <div>
            <flux:heading size="lg">Stock Opname</flux:heading>
            <flux:subheading>Sesuaikan stok fisik gudang dengan pencatatan sistem.</flux:subheading>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto mt-3 sm:mt-0">
            <flux:select wire:model.live="warehouse_id" placeholder="Pilih Gudang..." class="w-full sm:w-64">
                <flux:select.option value="">-- Pilih Gudang --</flux:select.option>
                @foreach ($warehouses as $w)
                    <flux:select.option value="{{ $w->id }}">{{ $w->name }}</flux:select.option>
                @endforeach
            </flux:select>
            
            <div class="flex gap-2 w-full sm:w-auto">
                <flux:button wire:click="loadHistory" variant="outline" icon="clock" class="flex-1 sm:flex-none">Riwayat</flux:button>
                @can('inventory.opname.create')
                <flux:button wire:click="save" variant="primary" icon="document-check" class="flex-1 sm:flex-none">Simpan</flux:button>
                @endcan
            </div>
        </div>
    </x-sticky-header>

    @if ($warehouse_id)
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden mb-6">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/50">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 rounded-lg shrink-0">
                                <flux:icon.clipboard-document-check class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm">Lembar Kerja Opname</h3>
                                <p class="text-xs text-zinc-500">Sesuaikan jumlah fisik atau gunakan scanner barcode.</p>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                            <flux:radio.group wire:model.live="inputMode" variant="segmented" class="flex w-full sm:w-auto">
                                <flux:radio value="manual" label="Input Manual" class="flex-1" />
                                <flux:radio value="barcode" label="Scan Barcode" class="flex-1" />
                            </flux:radio.group>
                            
                            <div class="h-6 w-px bg-zinc-200 dark:bg-zinc-700 hidden sm:block"></div>
                            
                            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                                @if(count($availableItems) > 0)
                                    <flux:button wire:click="loadAllAvailableItems" size="sm" variant="outline" icon="arrow-down-tray" class="flex-1 sm:flex-none justify-center">
                                        Tarik Semua ({{ count($availableItems) }})
                                    </flux:button>
                                @endif
                                <div class="flex-1 sm:w-64">
                                    <flux:select wire:model.live="newItemId" placeholder="Tambah Barang Baru..." searchable>
                                        <flux:select.option value="">Cari barang...</flux:select.option>
                                        @foreach($availableItems as $ai)
                                            <flux:select.option value="{{ $ai->id }}">{{ $ai->code }} - {{ $ai->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Barcode / Camera Scanner Area --}}
                    @if($inputMode === 'barcode')
                        <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                            <div class="bg-indigo-50/50 dark:bg-indigo-500/5 border border-indigo-100 dark:border-indigo-500/20 p-4 rounded-xl flex flex-col sm:flex-row gap-3">
                                <div class="flex-1 relative">
                                    <flux:input type="text" wire:model.live.debounce.500ms="scanned_barcode" placeholder="Ketik/pindai barcode dengan alat..." icon="qr-code" autofocus class="bg-white dark:bg-zinc-900" />
                                </div>
                                <div class="flex gap-2 w-full sm:w-auto">
                                    <flux:button type="button" x-on:click="Flux.modal('camera-scanner-modal').show(); window.dispatchEvent(new Event('camera-scanner-modal-opened'))" variant="filled" icon="camera" class="flex-1 sm:flex-none" tooltip="Gunakan Kamera HP">Buka Kamera HP</flux:button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-zinc-500 bg-zinc-50/50 dark:bg-zinc-800/50 uppercase border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <th class="px-4 py-3 font-medium">Barang</th>
                                <th class="px-4 py-3 font-medium text-center w-24">Sistem</th>
                                <th class="px-4 py-3 font-medium text-center w-32">Aktual</th>
                                <th class="px-4 py-3 font-medium text-center w-24">Selisih</th>
                                <th class="px-4 py-3 font-medium w-48">Alasan</th>
                                <th class="px-4 py-3 font-medium">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($items as $item)
                                <tr wire:key="opname-row-{{ $item->id }}-{{ $warehouse_id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors" x-data="{ 
                                    sys: {{ $opnameData[$item->id]['system_stock'] ?? 0 }},
                                    act: @entangle('opnameData.'.$item->id.'.actual_stock').live,
                                    get diff() {
                                        if (this.act === '') return 0;
                                        return (parseInt(this.act) || 0) - this.sys;
                                    }
                                }">
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col">
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->name }}</span>
                                            <span class="text-[10px] font-mono text-zinc-500">{{ $item->code }}</span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-4 py-3 text-center">
                                        <div class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 bg-zinc-100 dark:bg-zinc-800 rounded font-semibold text-zinc-700 dark:text-zinc-300">
                                            {{ $opnameData[$item->id]['system_stock'] ?? 0 }}
                                        </div>
                                    </td>
                                    
                                    <td class="px-4 py-3">
                                        <flux:input type="number" wire:model.live="opnameData.{{ $item->id }}.actual_stock" placeholder="0" class="w-full text-center" :disabled="$item->requires_label" />
                                        @if($item->requires_label)
                                            <p class="text-[10px] text-zinc-500 mt-1 text-center">Gunakan scanner</p>
                                        @endif
                                    </td>
                                    
                                    <td class="px-4 py-3 text-center">
                                        <span x-text="diff > 0 ? '+' + diff : diff" 
                                              :class="{
                                                'text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-500/10 px-2 py-1 rounded': diff > 0,
                                                'text-rose-600 dark:text-rose-400 font-bold bg-rose-50 dark:bg-rose-500/10 px-2 py-1 rounded': diff < 0,
                                                'text-zinc-400': diff === 0
                                              }">
                                        </span>
                                    </td>
                                    
                                    <td class="px-4 py-3">
                                        <div x-show="diff !== 0" x-transition>
                                            <flux:select wire:model="opnameData.{{ $item->id }}.reason" placeholder="Pilih alasan..." class="w-full">
                                                <flux:select.option value="Salah Hitung">Salah Hitung</flux:select.option>
                                                <flux:select.option value="Barang Ditemukan">Barang Ditemukan (+)</flux:select.option>
                                                <flux:select.option value="Kelebihan Terima">Kelebihan Terima (+)</flux:select.option>
                                                <flux:select.option value="Hilang">Hilang (-)</flux:select.option>
                                                <flux:select.option value="Rusak">Rusak (-)</flux:select.option>
                                                <flux:select.option value="Kadaluarsa">Kadaluarsa (-)</flux:select.option>
                                                <flux:select.option value="Lainnya">Lainnya...</flux:select.option>
                                            </flux:select>
                                            @error('opnameData.'.$item->id.'.reason')
                                                <div class="text-xs text-red-500 mt-1">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </td>
                                    
                                    <td class="px-4 py-3">
                                        <div x-show="diff !== 0" x-transition>
                                            <flux:input wire:model="opnameData.{{ $item->id }}.notes" placeholder="Catatan opsional..." class="w-full" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    
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

    {{-- History Modal --}}
    <flux:modal name="history-modal" class="md:max-w-4xl" @open-history-modal.window="$el.showModal()">
        <div class="flex flex-col gap-4">
            <div class="flex justify-between items-center">
                <div>
                    <flux:heading size="lg">Riwayat Gudang</flux:heading>
                    <flux:subheading>Daftar riwayat pergerakan stok dan dokumen opname (50 terakhir).</flux:subheading>
                </div>
            </div>

            <div class="flex border-b border-zinc-200 dark:border-zinc-700">
                <button wire:click="setTab('opname')" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'opname' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                    Dokumen Opname
                </button>
                <button wire:click="setTab('movement')" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'movement' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                    Kartu Stok (Pergerakan)
                </button>
            </div>

            <div class="overflow-y-auto max-h-[60vh] -mx-4 px-4">
                @if($activeTab === 'opname')
                    {{-- Daftar Dokumen (Grouped) --}}
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 uppercase sticky top-0">
                            <tr>
                                <th class="px-4 py-2">Tanggal</th>
                                <th class="px-4 py-2">No. Dokumen</th>
                                <th class="px-4 py-2">Gudang</th>
                                <th class="px-4 py-2 text-center">Jml. Barang</th>
                                <th class="px-4 py-2 text-center">Total Selisih</th>
                                <th class="px-4 py-2">Petugas</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($historyAdjustments as $doc)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($doc['adjustment_date'])->format('d M Y') }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $doc['reference_number'] }}</td>
                                    <td class="px-4 py-3">{{ $doc['warehouse'] }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-xs font-semibold px-2 py-0.5 rounded-full">
                                            {{ $doc['total_items'] }} barang
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-bold {{ $doc['total_selisih'] > 0 ? 'text-emerald-500' : ($doc['total_selisih'] < 0 ? 'text-rose-500' : 'text-zinc-400') }}">
                                            {{ $doc['total_selisih'] > 0 ? '+' : '' }}{{ $doc['total_selisih'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-500">{{ $doc['petugas'] }}</td>
                                    <td class="px-4 py-3">
                                        <flux:button wire:click="loadDocumentDetail('{{ $doc['reference_number'] }}')" size="xs" variant="outline" icon="eye">Detail</flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">Belum ada riwayat opname.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 uppercase sticky top-0">
                            <tr>
                                <th class="px-4 py-2">Waktu</th>
                                <th class="px-4 py-2">No. Dokumen</th>
                                <th class="px-4 py-2">Tipe</th>
                                <th class="px-4 py-2">Barang</th>
                                <th class="px-4 py-2 text-center">S. Awal</th>
                                <th class="px-4 py-2 text-center">Jumlah</th>
                                <th class="px-4 py-2 text-center">S. Akhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($historyMovements as $mov)
                                <tr>
                                    <td class="px-4 py-2">{{ $mov->created_at->format('d M Y H:i') }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-zinc-500">{{ $mov->reference_number ?? '-' }}</td>
                                    <td class="px-4 py-2 uppercase text-xs font-bold text-zinc-500">{{ $mov->type }}</td>
                                    <td class="px-4 py-2">{{ $mov->item?->name ?? 'Barang Dihapus' }}</td>
                                    <td class="px-4 py-2 text-center text-zinc-500">{{ $mov->stock_before }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="font-bold {{ $mov->quantity > 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                                            {{ $mov->quantity > 0 ? '+' : '' }}{{ $mov->quantity }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center font-bold">{{ $mov->stock_after }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">Belum ada riwayat pergerakan stok.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">Tutup</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- Document Detail Popup Modal --}}
    <flux:modal name="document-detail-modal" class="md:max-w-3xl" @open-document-detail-modal.window="$el.showModal()">
        <div class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Detail Dokumen Opname</flux:heading>
                <div class="mt-1 inline-flex items-center gap-2 font-mono text-xs bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-2.5 py-1 rounded-md border border-blue-200 dark:border-blue-700">
                    <flux:icon.document-text class="w-3.5 h-3.5" />
                    {{ $selectedDocument }}
                </div>
            </div>

            <div class="overflow-y-auto max-h-[60vh] -mx-4 px-4">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 uppercase sticky top-0">
                        <tr>
                            <th class="px-4 py-2">Barang</th>
                            <th class="px-4 py-2 text-center">Stok Sistem</th>
                            <th class="px-4 py-2 text-center">Stok Aktual</th>
                            <th class="px-4 py-2 text-center">Selisih</th>
                            <th class="px-4 py-2">Alasan</th>
                            <th class="px-4 py-2">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($documentDetail as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row['item']['name'] ?? 'Barang Dihapus' }}</div>
                                    <div class="text-xs font-mono text-zinc-400">{{ $row['item']['code'] ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-center text-zinc-500">{{ $row['system_stock'] }}</td>
                                <td class="px-4 py-3 text-center font-semibold">{{ $row['actual_stock'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-bold text-sm {{ $row['difference'] > 0 ? 'text-emerald-500' : ($row['difference'] < 0 ? 'text-rose-500' : 'text-zinc-400') }}">
                                        {{ $row['difference'] > 0 ? '+' : '' }}{{ $row['difference'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $row['reason'] }}</td>
                                <td class="px-4 py-3 text-xs text-zinc-500">{{ $row['notes'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">Tutup</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
    <!-- Komponen Global Scanner Kamera -->
    <x-camera-scanner />
</div>
