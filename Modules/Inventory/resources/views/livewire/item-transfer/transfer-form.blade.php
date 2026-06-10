<?php

use function Livewire\Volt\{state, rules, on};
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferItem;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\ItemLabel;
use Illuminate\Support\Facades\DB;
use Flux\Flux;

state([
    'from_warehouse_id' => '',
    'to_warehouse_id' => '',
    'transfer_date' => date('Y-m-d'),
    'notes' => '',
    'transfer_items' => [], // Format: [['item_id' => x, 'quantity' => y, 'available_stock' => z, 'item_name' => 'Name', 'sku' => 'SKU', 'requires_label' => bool, 'labels' => [id1 => code1, id2 => code2]]]
    
    // For item selection dropdown
    'selected_item_data' => null, // Menyimpan data barang sementara yang dipilih dari suggestion
    'selected_item_qty' => 1,
    'search' => '',
    
    // For barcode scanner
    'scanned_barcode' => '',
]);

rules([
    'from_warehouse_id' => 'required|exists:warehouses,id',
    'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
    'transfer_date' => 'required|date',
    'transfer_items' => 'required|array|min:1',
    'transfer_items.*.item_id' => 'required|exists:items,id',
    'transfer_items.*.quantity' => 'required|numeric|min:1',
]);

$results = \Livewire\Volt\computed(function () {
    if (strlen($this->search) < 2) {
        return collect();
    }

    $query = Item::query()
        ->where(function ($q) {
            $q->where('name', 'like', '%' . $this->search . '%')
              ->orWhere('code', 'like', '%' . $this->search . '%');
        });

    if ($this->from_warehouse_id) {
        $query->whereHas('warehouses', function ($q) {
            $q->where('warehouse_id', $this->from_warehouse_id)
              ->where('stock', '>', 0);
        })->with(['warehouses' => function ($q) {
            $q->where('warehouse_id', $this->from_warehouse_id);
        }]);
    }

    return $query->take(10)->get();
});

// Reset items jika gudang asal diubah
$updatedFromWarehouseId = function ($value) {
    $this->transfer_items = []; 
    $this->selected_item_data = null;
    $this->selected_item_qty = 1;
    $this->search = '';
    $this->scanned_barcode = '';
};

$selectItem = function (Item $item) {
    $stock = 0;
    if ($this->from_warehouse_id) {
        $warehousePivot = $item->warehouses->firstWhere('id', $this->from_warehouse_id);
        $stock = $warehousePivot ? $warehousePivot->pivot->stock : 0;
    }

    $this->selected_item_data = [
        'id' => $item->id,
        'name' => $item->name,
        'code' => $item->code,
        'stock' => $stock,
        'requires_label' => $item->requires_label,
    ];

    $this->search = '';
};

$addItem = function () {
    if (!$this->from_warehouse_id) {
        Flux::toast('Pilih gudang asal terlebih dahulu.', variant: 'danger');
        return;
    }
    
    if (!$this->selected_item_data || !$this->selected_item_qty) {
        Flux::toast('Pilih barang dari pencarian dan tentukan kuantitas.', variant: 'danger');
        return;
    }
    
    // Check if item requires label. If so, they must scan it instead of manually adding quantity here.
    if ($this->selected_item_data['requires_label']) {
        Flux::toast('Barang ini memiliki Serial Number. Silakan gunakan pemindai barcode untuk menambahkannya.', variant: 'warning');
        return;
    }
    
    // Check if already in list
    $existsIndex = collect($this->transfer_items)->search(fn($item) => $item['item_id'] === $this->selected_item_data['id']);
    
    if ($existsIndex !== false) {
        Flux::toast('Barang ini sudah ada di daftar transfer.', variant: 'warning');
        return;
    }
    
    $availableStock = $this->selected_item_data['stock'] ?? 0;
    
    if ($this->selected_item_qty > $availableStock) {
        Flux::toast('Kuantitas melebihi stok yang tersedia (' . $availableStock . ').', variant: 'danger');
        return;
    }
    
    $this->transfer_items[] = [
        'item_id' => $this->selected_item_data['id'],
        'item_name' => $this->selected_item_data['name'],
        'sku' => $this->selected_item_data['code'],
        'quantity' => (int) $this->selected_item_qty,
        'available_stock' => $availableStock,
        'requires_label' => false,
        'labels' => [],
    ];
    
    // Reset selection
    $this->selected_item_data = null;
    $this->selected_item_qty = 1;
};

$scanBarcode = function () {
    if (!$this->from_warehouse_id) {
        Flux::toast('Pilih gudang asal terlebih dahulu.', variant: 'danger');
        $this->scanned_barcode = '';
        return;
    }
    
    $code = trim($this->scanned_barcode);
    if (empty($code)) return;
    
    // Cari ItemLabel
    $label = ItemLabel::with(['item.warehouses' => function($q) {
            $q->where('warehouse_id', $this->from_warehouse_id);
        }])
        ->where('label_code', $code)
        ->first();
        
    if (!$label) {
        Flux::toast('Barcode tidak terdaftar dalam sistem.', variant: 'danger');
        $this->scanned_barcode = '';
        return;
    }
    
    if ($label->warehouse_id != $this->from_warehouse_id) {
        Flux::toast('Fisik barang ini berada di lokasi gudang yang berbeda.', variant: 'danger');
        $this->scanned_barcode = '';
        return;
    }
    
    if ($label->status !== 'in_stock') {
        Flux::toast('Status barang tidak tersedia (Mungkin sudah terjual/rusak/transit).', variant: 'danger');
        $this->scanned_barcode = '';
        return;
    }
    
    $item = $label->item;
    $availableStock = 0;
    $warehousePivot = $item->warehouses->first();
    if ($warehousePivot) {
        $availableStock = $warehousePivot->pivot->stock;
    }
    
    // Cek apakah item sudah ada di daftar transfer
    $existsIndex = collect($this->transfer_items)->search(fn($tItem) => $tItem['item_id'] === $item->id);
    
    if ($existsIndex !== false) {
        // Cek apakah label sudah ada
        if (isset($this->transfer_items[$existsIndex]['labels'][$label->id])) {
            Flux::toast('Barcode ini sudah dipindai.', variant: 'warning');
            $this->scanned_barcode = '';
            return;
        }
        
        // Cek stok maksimum
        if ($this->transfer_items[$existsIndex]['quantity'] >= $availableStock) {
            Flux::toast('Gagal. Seluruh stok untuk barang ini sudah dimasukkan.', variant: 'danger');
            $this->scanned_barcode = '';
            return;
        }
        
        // Tambahkan ke array
        $this->transfer_items[$existsIndex]['labels'][$label->id] = $label->label_code;
        $this->transfer_items[$existsIndex]['quantity'] = count($this->transfer_items[$existsIndex]['labels']);
    } else {
        // Tambahkan sebagai baris baru
        $this->transfer_items[] = [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'sku' => $item->code,
            'quantity' => 1,
            'available_stock' => $availableStock,
            'requires_label' => true,
            'labels' => [$label->id => $label->label_code],
        ];
    }
    
    // Dispatch js event if needed for audio play or visual feedback
    Flux::toast('Berhasil dipindai: ' . $item->name, variant: 'success');
    $this->scanned_barcode = '';
};

$removeItem = function ($index) {
    unset($this->transfer_items[$index]);
    $this->transfer_items = array_values($this->transfer_items);
};

$removeLabel = function ($itemIndex, $labelId) {
    unset($this->transfer_items[$itemIndex]['labels'][$labelId]);
    $this->transfer_items[$itemIndex]['quantity'] = count($this->transfer_items[$itemIndex]['labels']);
    
    // Jika qty 0, hapus baris sekalian
    if ($this->transfer_items[$itemIndex]['quantity'] === 0) {
        $this->removeItem($itemIndex);
    }
};

$generateReferenceNumber = function () {
    $prefix = 'TF-' . date('ymd') . '-';
    $lastTransfer = StockTransfer::where('reference_number', 'like', $prefix . '%')
        ->orderBy('id', 'desc')
        ->first();

    if (!$lastTransfer) {
        return $prefix . '0001';
    }

    $lastNumber = (int) substr($lastTransfer->reference_number, -4);
    $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    return $prefix . $newNumber;
};

$save = function () {
    $this->validate();

    // Secondary validation for stock availability
    foreach ($this->transfer_items as $tItem) {
        $warehouse = Warehouse::find($this->from_warehouse_id);
        $currentStock = $warehouse->items()->where('item_id', $tItem['item_id'])->first()->pivot->stock ?? 0;
        if ($tItem['quantity'] > $currentStock) {
            $this->addError('transfer_items', "Stok untuk barang {$tItem['item_name']} tidak mencukupi di gudang asal.");
            return;
        }
    }

    DB::beginTransaction();

    try {
        $referenceNumber = $this->generateReferenceNumber();

        // 1. Create StockTransfer (Header)
        $transfer = StockTransfer::create([
            'reference_number' => $referenceNumber,
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id' => $this->to_warehouse_id,
            'status' => 'completed',
            'transfer_date' => $this->transfer_date,
            'notes' => $this->notes,
            'user_id' => auth()->id(),
        ]);

        $fromWarehouse = Warehouse::find($this->from_warehouse_id);
        $toWarehouse = Warehouse::find($this->to_warehouse_id);

        // 2. Process each item
        foreach ($this->transfer_items as $tItem) {
            // A. Create StockTransferItem (Detail)
            StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                'item_id' => $tItem['item_id'],
                'quantity' => $tItem['quantity'],
            ]);
            
            // UPDATE LABELS JIKA ADA
            if (!empty($tItem['labels'])) {
                $labelIds = array_keys($tItem['labels']);
                ItemLabel::whereIn('id', $labelIds)->update([
                    'warehouse_id' => $this->to_warehouse_id,
                ]);
            }

            // B. Update stock in From Warehouse (decrease)
            $fromCurrentStock = $fromWarehouse->items()->where('item_id', $tItem['item_id'])->first()->pivot->stock ?? 0;
            $fromWarehouse->items()->syncWithoutDetaching([
                $tItem['item_id'] => ['stock' => $fromCurrentStock - $tItem['quantity']]
            ]);

            // C. Update stock in To Warehouse (increase)
            $toCurrentStock = $toWarehouse->items()->where('item_id', $tItem['item_id'])->first()->pivot->stock ?? 0;
            if ($toWarehouse->items()->where('item_id', $tItem['item_id'])->exists()) {
                $toWarehouse->items()->updateExistingPivot($tItem['item_id'], [
                    'stock' => $toCurrentStock + $tItem['quantity']
                ]);
            } else {
                $toWarehouse->items()->attach($tItem['item_id'], [
                    'stock' => $tItem['quantity']
                ]);
            }

            // D. Record StockMovements
            // OUT Movement
            StockMovement::create([
                'item_id' => $tItem['item_id'],
                'warehouse_id' => $this->from_warehouse_id,
                'type' => 'transfer_out',
                'quantity' => -$tItem['quantity'],
                'stock_before' => $fromCurrentStock,
                'stock_after' => $fromCurrentStock - $tItem['quantity'],
                'reference_number' => $referenceNumber,
                'date' => $this->transfer_date,
                'notes' => 'Transfer keluar ke ' . $toWarehouse->name,
                'user_id' => auth()->id(),
            ]);

            // IN Movement
            StockMovement::create([
                'item_id' => $tItem['item_id'],
                'warehouse_id' => $this->to_warehouse_id,
                'type' => 'transfer_in',
                'quantity' => $tItem['quantity'],
                'stock_before' => $toCurrentStock,
                'stock_after' => $toCurrentStock + $tItem['quantity'],
                'reference_number' => $referenceNumber,
                'date' => $this->transfer_date,
                'notes' => 'Transfer masuk dari ' . $fromWarehouse->name,
                'user_id' => auth()->id(),
            ]);
        }

        DB::commit();

        Flux::toast('Transfer barang berhasil diproses.', variant: 'success');
        
        // Reset state
        $this->transfer_items = [];
        $this->from_warehouse_id = '';
        $this->to_warehouse_id = '';
        $this->notes = '';
        $this->search = '';
        $this->scanned_barcode = '';
        
        // Dispatch local event to parent (for closing modal, etc.)
        $this->dispatch('transfer-saved');
        
        // Dispatch global broadcasting event (Reverb) to all listening clients
        \App\Events\InventoryUpdated::safeDispatch('Transfer barang berhasil diproses: ' . $referenceNumber);
        
        // Kirim notifikasi ke manajer/admin
        $recipients = \App\Models\User::permission('inventory.notifikasi.view')
            ->orWhereHas('roles', fn($q) => $q->where('name', 'Super Admin'))
            ->get();
        if ($recipients->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\WarehouseTransferNotification($referenceNumber, "Terdapat transfer barang baru (No. {$referenceNumber}) yang menunggu konfirmasi."));
        }
        
        // Close Modal
        Flux::modal('create-transfer-modal')->close();

    } catch (\Exception $e) {
        DB::rollBack();
        Flux::toast('Gagal memproses transfer: ' . $e->getMessage(), variant: 'danger');
    }
};

?>

<div @barcode-scanned.window="$wire.set('scanned_barcode', $event.detail.code); $wire.scanBarcode()">
    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 px-3">
        <!-- Sidebar: Informasi Utama -->
        <div class="lg:col-span-1 space-y-4 md:space-y-6">
            <flux:heading size="lg" class="mb-4">Detail Dokumen</flux:heading>
            <div class="space-y-4">
                <flux:input type="date" wire:model="transfer_date" label="Tanggal Transfer" />

                <flux:select wire:model.live="from_warehouse_id" label="Gudang Asal" placeholder="Pilih gudang asal">
                    @foreach(Warehouse::all() as $warehouse)
                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:select wire:model.live="to_warehouse_id" label="Gudang Tujuan" placeholder="Pilih gudang tujuan">
                    @foreach(Warehouse::all() as $warehouse)
                        @if($warehouse->id != $from_warehouse_id)
                            <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                        @endif
                    @endforeach
                </flux:select>
                
                <flux:textarea wire:model="notes" label="Catatan Tambahan (Opsional)" rows="3" />
            </div>
        </div>

        <!-- Main Content: Daftar Barang -->
        <div class="mx-0 lg:col-span-2 space-y-4 md:space-y-6">

                <flux:heading size="lg" class="mb-4">Daftar Barang</flux:heading>
                
                @if(!$from_warehouse_id ||  !$to_warehouse_id)
                    <div class="p-3 md:p-4 bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 rounded-lg text-sm border border-amber-200 dark:border-amber-800">
                        Silakan pilih Gudang Asal dan Gudang Tujuan terlebih dahulu untuk melihat stok barang yang tersedia.
                    </div>
                @else
                    <!-- Input Method Tabs (Scan vs Manual) -->
                    <div x-data="{ mode: 'scan' }" class="mb-6">
                        <div class="flex gap-4 border-b border-zinc-200 dark:border-zinc-700 mb-4">
                            <button type="button" @click="mode = 'scan'" :class="mode === 'scan' ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-zinc-500'" class="pb-2 text-sm flex items-center gap-2">
                                <flux:icon.qr-code class="w-4 h-4" /> Pindai Barcode
                            </button>
                            <button type="button" @click="mode = 'manual'" :class="mode === 'manual' ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-zinc-500'" class="pb-2 text-sm flex items-center gap-2">
                                <flux:icon.magnifying-glass class="w-4 h-4" /> Pilih Manual
                            </button>
                        </div>
                        
                        <!-- Mode Scan -->
                        <div x-show="mode === 'scan'" class="bg-indigo-50/50 dark:bg-indigo-500/5 border border-indigo-100 dark:border-indigo-500/20 p-4 rounded-xl flex flex-col sm:flex-row gap-3">
                            <div class="flex-1 relative">
                                <flux:input type="text" wire:model="scanned_barcode" wire:keydown.enter="scanBarcode" placeholder="Ketik/pindai barcode..." icon="qr-code" autofocus class="bg-white dark:bg-zinc-900" />
                            </div>
                            <div class="flex gap-2 w-full sm:w-auto">
                                <flux:button type="button" wire:click="scanBarcode" variant="primary" class="flex-1 sm:flex-none">Cari</flux:button>
                                <flux:button type="button" x-on:click="Flux.modal('camera-scanner-modal').show(); window.dispatchEvent(new Event('camera-scanner-modal-opened'))" variant="filled" icon="camera" class="flex-1 sm:flex-none" tooltip="Gunakan Kamera HP">Kamera</flux:button>
                            </div>
                        </div>
                        
                        <!-- Mode Manual -->
                        <div x-show="mode === 'manual'" x-cloak class="flex flex-col sm:flex-row gap-3 items-start sm:items-end bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div class="flex-1 w-full">
                                @if($selected_item_data)
                                    <div class="flex items-center justify-between bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 px-3 py-2 rounded-lg">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium">{{ $selected_item_data['name'] }}</span>
                                            <span class="text-xs text-zinc-500">Stok: {{ $selected_item_data['stock'] }}</span>
                                        </div>
                                        <flux:button size="sm" variant="subtle" icon="x-mark" wire:click="$set('selected_item_data', null)" />
                                    </div>
                                @else
                                    <x-item-search-suggest 
                                        :search="$search" 
                                        :results="$this->results" 
                                        :warehouseId="$from_warehouse_id" 
                                        placeholder="Cari Barang Non-Serial..."
                                    />
                                @endif
                            </div>
                            <div class="w-full sm:w-32">
                                <flux:input type="number" wire:model="selected_item_qty" placeholder="Qty" min="1" tooltip="Jumlah Kuantitas" />
                            </div>
                            <div class="w-full sm:w-auto">
                                <flux:button type="button" wire:click="addItem" variant="filled" icon="plus" class="w-full">Tambah</flux:button>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Barang Terpilih (Responsive) -->
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                        <table class="w-full text-sm text-left text-zinc-600 dark:text-zinc-400 block sm:table">
                            <thead class="hidden sm:table-header-group text-xs text-zinc-700 uppercase bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-300">
                                <tr class="sm:table-row">
                                    <th scope="col" class="px-3 md:px-4 py-3">Barang</th>
                                    <th scope="col" class="px-3 md:px-4 py-3 text-center">Stok Gudang</th>
                                    <th scope="col" class="px-3 md:px-4 py-3 text-center w-32 md:w-40">Qty Transfer</th>
                                    <th scope="col" class="px-3 md:px-4 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="block sm:table-row-group">
                                @forelse($transfer_items as $index => $item)
                                    <tr class="block sm:table-row bg-white border-b dark:bg-zinc-900 dark:border-zinc-700 align-top p-4 sm:p-0">
                                        <td class="block sm:table-cell px-0 sm:px-3 md:px-4 py-2 sm:py-3 mb-3 sm:mb-0">
                                            <div class="font-medium text-base sm:text-sm text-zinc-900 dark:text-white">{{ $item['item_name'] }}</div>
                                            <div class="text-xs text-zinc-500">{{ $item['sku'] }}</div>
                                            
                                            {{-- Menampilkan daftar barcode/label di bawah nama --}}
                                            @if(!empty($item['labels']))
                                                <div class="mt-2 flex flex-wrap gap-1">
                                                    @foreach($item['labels'] as $lblId => $lblCode)
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20">
                                                            {{ $lblCode }}
                                                            <button type="button" wire:click="removeLabel({{ $index }}, {{ $lblId }})" class="hover:text-red-500 transition-colors">
                                                                <flux:icon.x-mark class="w-3 h-3" />
                                                            </button>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        
                                        <td class="flex sm:table-cell justify-between items-center px-0 sm:px-3 md:px-4 py-2 sm:py-3 text-center align-middle">
                                            <span class="sm:hidden text-xs font-semibold text-zinc-500 uppercase tracking-wider">Stok Gudang</span>
                                            <span class="font-medium sm:font-normal">{{ $item['available_stock'] }}</span>
                                        </td>
                                        
                                        <td class="flex sm:table-cell justify-between items-center px-0 sm:px-3 md:px-4 py-2 sm:py-3 text-center align-middle border-b sm:border-0 border-dashed border-zinc-200 dark:border-zinc-700 mb-3 pb-3 sm:mb-0 sm:pb-0">
                                            <span class="sm:hidden text-xs font-semibold text-zinc-500 uppercase tracking-wider">Qty Transfer</span>
                                            <div>
                                                @if(isset($item['requires_label']) && $item['requires_label'])
                                                    <div class="flex flex-col items-end sm:items-center justify-center gap-0.5">
                                                        <span class="font-bold text-lg text-indigo-600 dark:text-indigo-400">{{ $item['quantity'] }}</span>
                                                        <span class="text-[10px] text-zinc-400 uppercase tracking-wider font-semibold">Scanned</span>
                                                    </div>
                                                @else
                                                    <flux:input type="number" wire:model="transfer_items.{{ $index }}.quantity" min="1" max="{{ $item['available_stock'] }}" class="w-24 sm:w-full text-right sm:text-center" />
                                                @endif
                                            </div>
                                        </td>
                                        
                                        <td class="flex sm:table-cell justify-end sm:justify-center px-0 sm:px-3 md:px-4 py-0 sm:py-3 align-middle">
                                            <flux:button variant="danger" icon="trash" size="sm" wire:click="removeItem({{ $index }})" class="w-full sm:w-auto" tooltip="Hapus Barang" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="block sm:table-row">
                                        <td colspan="4" class="block sm:table-cell px-4 py-10 text-center text-zinc-500">
                                            <flux:icon.qr-code class="w-8 h-8 mx-auto mb-3 text-zinc-300 dark:text-zinc-600" />
                                            Mulai pindai barcode atau pilih barang secara manual.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @error('transfer_items')
                        <div class="mt-2 text-red-500 text-sm">{{ $message }}</div>
                    @enderror
                @endif

            <div class="flex flex-col sm:flex-row justify-end gap-3 mt-4">
                <flux:modal.close>
                    <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('Batal') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check" class="w-full sm:w-auto" :disabled="count($transfer_items) === 0">
                    {{ __('Proses Transfer') }}
                </flux:button>
            </div>
        </div>
    </form>

    <!-- Komponen Global Scanner Kamera -->
    <x-camera-scanner />
</div>
