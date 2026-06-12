<?php

use function Livewire\Volt\{state, on};
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseReceipt;
use Modules\Purchase\Models\PurchaseReceiptItem;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\ItemLabel;
use Modules\Inventory\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'purchaseOrder' => null,
    'items' => [],
    'notes' => '',
    'receipt_date' => Carbon::now()->format('Y-m-d'),
    'warehouses_list' => [],
]);

on(['open-receipt-modal' => function ($orderId) {
    $this->purchaseOrder = PurchaseOrder::with(['items.item', 'vendor'])->findOrFail($orderId);
    
    $this->receipt_date = Carbon::now()->format('Y-m-d');
    $this->warehouses_list = Warehouse::all();
    
    // Tentukan gudang default jika ada
    $defaultWarehouseId = '';

    // Siapkan daftar item yang belum diterima sepenuhnya
    $this->items = [];
    foreach ($this->purchaseOrder->items as $item) {
        $remaining = $item->quantity - $item->received_quantity;
        if ($remaining > 0) {
            $this->items[] = [
                'id' => $item->id,
                'item_id' => $item->item_id,
                'name' => $item->item->name,
                'code' => $item->item->code,
                'ordered' => $item->quantity,
                'received_past' => $item->received_quantity,
                'remaining' => $remaining,
                'requires_label' => $item->item->requires_label,
                'distributions' => [
                    ['warehouse_id' => '', 'qty' => '']
                ]
            ];
        }
    }
    
    $this->show = true;
}]);

$addDistribution = function ($index) {
    // Pola UX Kelas Dunia: 'Smart Reuse'
    // Cek apakah ada baris yang saat ini kosong/0
    foreach ($this->items[$index]['distributions'] as $distIndex => $dist) {
        if (empty($dist['warehouse_id']) || empty($dist['qty']) || (int)$dist['qty'] <= 0) {
            // Alih-alih membuat baris baru, arahkan fokus pengguna ke baris yang nganggur
            $this->js("
                let row = document.getElementById('dist-row-{$index}-{$distIndex}');
                if(row) {
                    row.classList.add('ring-2', 'ring-indigo-400', 'ring-offset-4', 'dark:ring-offset-zinc-900', 'rounded-lg', 'transition-all', 'duration-300');
                    setTimeout(() => {
                        row.classList.remove('ring-2', 'ring-indigo-400', 'ring-offset-4', 'dark:ring-offset-zinc-900', 'rounded-lg');
                    }, 1500);
                }
            ");
            return;
        }
    }

    $this->items[$index]['distributions'][] = ['warehouse_id' => '', 'qty' => ''];
};

$removeDistribution = function ($itemIndex, $distIndex) {
    unset($this->items[$itemIndex]['distributions'][$distIndex]);
    $this->items[$itemIndex]['distributions'] = array_values($this->items[$itemIndex]['distributions']);
};

$updated = function ($property, $value) {
    // Koreksi otomatis (auto-clamp) jika input qty melebihi batas sisa pesanan
    if (preg_match('/items\.(\d+)\.distributions\.(\d+)\.qty/', $property, $matches)) {
        $itemIndex = (int) $matches[1];
        $distIndex = (int) $matches[2];
        
        $item = $this->items[$itemIndex];
        $remaining = (int) $item['remaining'];
        
        // Hitung total dari baris distribusi lain selain yang sedang diedit
        $otherTotal = 0;
        foreach ($item['distributions'] as $i => $dist) {
            if ($i !== $distIndex) {
                $otherTotal += (int) ($dist['qty'] ?? 0);
            }
        }
        
        $maxAllowed = max(0, $remaining - $otherTotal);
        $inputValue = (int) ($value ?? 0);
        
        if ($inputValue > $maxAllowed) {
            // Batasi angka ke jumlah maksimal yang diperbolehkan
            $this->items[$itemIndex]['distributions'][$distIndex]['qty'] = $maxAllowed > 0 ? $maxAllowed : '';
        }
    }
};

$save = function () {
    // Pembersihan Otomatis (Auto-Cleanup): 
    // Buang baris distribusi yang tidak diisi (qty kosong/0 atau gudang tidak dipilih) 
    // agar tidak memicu error validasi yang membingungkan pengguna.
    // Pembersihan Otomatis (Auto-Cleanup) dikembalikan:
    // Hapus distribusi yang masih berupa draf kosong murni dari payload sebelum divalidasi
    // agar barang yang memang tidak ingin diterima saat ini tidak memicu error validasi server.
    foreach ($this->items as $index => $item) {
        $cleanDistributions = [];
        foreach ($item['distributions'] as $dist) {
            // Hanya simpan jika gudang dan qty keduanya valid terisi
            if (!empty($dist['warehouse_id']) && (int)($dist['qty'] ?? 0) > 0) {
                $cleanDistributions[] = $dist;
            }
        }
        $this->items[$index]['distributions'] = $cleanDistributions;
    }

    \Illuminate\Support\Facades\Log::info('Submitting 1 or more warehouses:', $this->items);

    try {
        $this->validate([
            'receipt_date' => 'required|date',
            'items.*.distributions.*.qty' => 'required|integer|min:1',
            'items.*.distributions.*.warehouse_id' => 'required|exists:warehouses,id',
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Illuminate\Support\Facades\Log::error('Validation Error: ' . json_encode($e->errors()));
        \Flux::toast(
            heading: 'Validasi Sistem',
            text: 'Data tidak valid: ' . json_encode($e->errors()),
            variant: 'danger'
        );
        return;
    }

    // Validasi Custom: Gudang tidak boleh duplikat, dan qty tidak boleh lebih dari sisa
    $totalReceiving = 0;
    foreach ($this->items as $index => $item) {
        $itemTotal = 0;
        $warehouseIds = [];
        
        foreach ($item['distributions'] as $dist) {
            if (in_array($dist['warehouse_id'], $warehouseIds)) {
                \Flux::toast(
                    heading: 'Validasi Gagal',
                    text: 'Gudang penyimpanan untuk barang ' . $item['name'] . ' tidak boleh ada yang sama.',
                    variant: 'danger'
                );
                return;
            }
            $warehouseIds[] = $dist['warehouse_id'];
            
            $itemTotal += (int) $dist['qty'];
        }
        
        if ($itemTotal > $item['remaining']) {
            \Flux::toast(
                heading: 'Validasi Gagal',
                text: 'Total kuantitas diterima untuk barang ' . $item['name'] . ' melebihi sisa pesanan (' . $item['remaining'] . ').',
                variant: 'danger'
            );
            return;
        }

        $totalReceiving += $itemTotal;
    }
    
    if ($totalReceiving <= 0) {
        \Flux::toast(
            heading: 'Error',
            text: 'Masukkan setidaknya satu kuantitas barang yang diterima.',
            variant: 'danger'
        );
        return;
    }

    DB::beginTransaction();
    try {
        // 1. Buat Header Receipt
        $receipt = PurchaseReceipt::create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'receipt_number' => 'RCV-' . date('Ymd') . '-' . rand(1000, 9999),
            'receipt_date' => $this->receipt_date,
            'receiver_name' => auth()->user()->name ?? 'System',
            'notes' => $this->notes,
            'user_id' => auth()->id(),
        ]);

        $allFullyReceived = true;
        $generatedLabelsCount = 0;
        $generatedLabelIds = [];

        foreach ($this->items as $inputItem) {
            $totalReceivedForItem = collect($inputItem['distributions'])->sum('qty');
            
            if ($totalReceivedForItem > 0) {
                // Pastikan tidak menerima lebih dari sisa
                $receiveQty = min($totalReceivedForItem, $inputItem['remaining']);

                // 2. Buat Detail Receipt (satu row total untuk histori penerimaan)
                PurchaseReceiptItem::create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $inputItem['id'],
                    'item_id' => $inputItem['item_id'],
                    'quantity' => $receiveQty,
                ]);

                // 3. Update PO Item received_quantity
                $poItem = \Modules\Purchase\Models\PurchaseOrderItem::find($inputItem['id']);
                $poItem->received_quantity += $receiveQty;
                $poItem->save();

                $itemModel = \Modules\Inventory\Models\Item::find($inputItem['item_id']);
                
                $remainingToDistribute = $receiveQty;

                foreach ($inputItem['distributions'] as $dist) {
                    $distQty = $dist['qty'];
                    if ($distQty <= 0) continue;
                    
                    // Batasi jika qty distribusi melebih sisa total (edge case keamanan)
                    if ($distQty > $remainingToDistribute) {
                        $distQty = $remainingToDistribute;
                    }
                    if ($distQty <= 0) break;
                    
                    $remainingToDistribute -= $distQty;

                    // 4. Update Inventory Stock Movement
                    $targetWarehouseId = $dist['warehouse_id'];

                    $stockBefore = DB::table('item_warehouse')->where('item_id', $itemModel->id)->where('warehouse_id', $targetWarehouseId)->value('stock') ?? 0;
                    $stockAfter = $stockBefore + $distQty;

                    StockMovement::create([
                        'item_id' => $itemModel->id,
                        'warehouse_id' => $targetWarehouseId,
                        'type' => 'in',
                        'quantity' => $distQty,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'reference_number' => $receipt->receipt_number,
                        'date' => $this->receipt_date,
                        'notes' => 'Penerimaan dari PO: ' . $this->purchaseOrder->po_number,
                        'user_id' => auth()->id(),
                    ]);

                    // 5. Generate Labels jika required
                    if ($inputItem['requires_label']) {
                        for ($i = 0; $i < $distQty; $i++) {
                            // Generate unique 6-char alphanumeric code
                            do {
                                $code = strtoupper(\Illuminate\Support\Str::random(6));
                            } while (\Modules\Inventory\Models\ItemLabel::where('label_code', $code)->exists());

                            $label = \Modules\Inventory\Models\ItemLabel::create([
                                'item_id' => $itemModel->id,
                                'label_code' => $code,
                                'status' => 'in_stock',
                                'warehouse_id' => $dist['warehouse_id'],
                                'notes' => 'Dari PO: ' . $this->purchaseOrder->po_number,
                            ]);
                            $generatedLabelIds[] = $label->id;
                            $generatedLabelsCount++;
                        }
                    }
                }
            }

            // Cek jika item ini masih ada sisa setelah diupdate
            if ($totalReceivedForItem < $inputItem['remaining']) {
                $allFullyReceived = false;
            }
        }

        // Cek item lain yang tidak ada di modal (sudah diterima penuh sebelumnya)
        // Jika ada item di PO yang total received_quantity < quantity, berarti belum sepenuhnya
        $totalPOItems = $this->purchaseOrder->items()->sum('quantity');
        $totalReceivedItems = $this->purchaseOrder->items()->sum('received_quantity');
        
        // 6. Update PO Status
        if ($totalReceivedItems >= $totalPOItems) {
            $this->purchaseOrder->status = 'completed';
        } else {
            $this->purchaseOrder->status = 'partially_received';
        }
        $this->purchaseOrder->save();

        DB::commit();

        $this->show = false;
        $this->dispatch('status-updated'); // Refresh Kanban
        
        // Buka modal cetak label otomatis jika ada label yang digenerate
        if (count($generatedLabelIds) > 0) {
            $this->dispatch('open-print-labels', labelIds: $generatedLabelIds);
        }
        
        $msg = "Penerimaan barang berhasil disimpan.";
        if ($generatedLabelsCount > 0) {
            $msg .= " $generatedLabelsCount Label Serial berhasil di-generate otomatis.";
        }
        \Flux::toast(
            heading: 'Berhasil',
            text: $msg,
            variant: 'success'
        );

    } catch (\Exception $e) {
        DB::rollBack();
        \Illuminate\Support\Facades\Log::error('Penerimaan Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        \Flux::toast(
            heading: 'Gagal',
            text: 'Terjadi kesalahan sistem: ' . $e->getMessage(),
            variant: 'danger'
        );
    }
};

?>

<flux:modal wire:model="show" class="w-full max-w-[950px] space-y-6">
    @if($purchaseOrder)
    <div>
        <flux:heading size="lg">📦 Form Penerimaan Barang</flux:heading>
        <flux:subheading>Terima barang fisik dari PO: <span class="font-bold text-zinc-900 dark:text-white">{{ $purchaseOrder->po_number }}</span></flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-4">
        <flux:input wire:model="receipt_date" type="date" label="Tanggal Terima" />
    </div>

    <div class="space-y-4 mt-6">
        @forelse($items as $index => $item)
            <div class="p-4 border border-zinc-200 dark:border-zinc-700 rounded-xl bg-white dark:bg-zinc-800/50 flex flex-col gap-4">
                <div class="flex justify-between items-start gap-4">
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-white">{{ $item['name'] }}</div>
                        <div class="text-xs text-zinc-500 mb-1">{{ $item['code'] }}</div>
                        @if($item['requires_label'])
                            <flux:badge size="sm" color="indigo" class="mt-1">Auto-Label</flux:badge>
                        @endif
                    </div>
                    <div class="text-right whitespace-nowrap">
                        <div class="text-xs text-zinc-500">Sisa / Pesan</div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-white">
                            <span class="text-lg">{{ $item['remaining'] }}</span> / {{ $item['ordered'] }}
                        </div>
                        @if($item['received_past'] > 0)
                            <div class="text-xs text-zinc-400 mt-1">Sdh: {{ $item['received_past'] }}</div>
                        @endif
                    </div>
                </div>
                
                @php
                    $currentTotal = collect($item['distributions'])->sum(fn($d) => (int)($d['qty'] ?? 0));
                    $whIds = collect($item['distributions'])->pluck('warehouse_id')->filter()->toArray();
                    $hasDuplicates = count($whIds) !== count(array_unique($whIds));
                    $allDistributionsFilled = collect($item['distributions'])->every(fn($d) => !empty($d['warehouse_id']) && !empty($d['qty']) && (int)$d['qty'] > 0);
                @endphp
                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-700/50 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Distribusi Gudang</span>
                        <div class="flex items-center gap-2">
                            @if($currentTotal > $item['remaining'])
                                <span class="text-xs text-red-500 font-semibold bg-red-50 dark:bg-red-500/10 px-2 py-0.5 rounded border border-red-200 dark:border-red-500/20">Total melebihi sisa! ({{ $currentTotal }}/{{ $item['remaining'] }})</span>
                            @endif
                            @if($hasDuplicates)
                                <span class="text-xs text-red-500 font-semibold bg-red-50 dark:bg-red-500/10 px-2 py-0.5 rounded border border-red-200 dark:border-red-500/20">Lokasi Gudang ganda!</span>
                            @endif
                        </div>
                    </div>
                    
                    @foreach($item['distributions'] as $distIndex => $dist)
                        @php
                            $isRowEmpty = empty($dist['qty']);
                            $isQuotaFull = $currentTotal >= $item['remaining'];
                            $shouldDisable = $isQuotaFull && $isRowEmpty;
                        @endphp
                        <div id="dist-row-{{ $index }}-{{ $distIndex }}" 
                             x-data="{ warehouseId: '{{ $dist['warehouse_id'] }}' }" 
                             x-on:focusin="
                                 let dists = $wire.items[{{ $index }}].distributions;
                                 let cleaned = dists.filter((d, i) => {
                                     if (i === {{ $distIndex }}) return true;
                                     return d.warehouse_id && d.warehouse_id !== '' && parseInt(d.qty || 0) > 0;
                                 });
                                 if (cleaned.length !== dists.length) {
                                     $wire.items[{{ $index }}].distributions = cleaned;
                                 }
                             "
                             class="flex gap-3 items-end opacity-100 transition-opacity duration-200 {{ $shouldDisable ? 'opacity-50 grayscale-[50%]' : '' }}">
                            <div class="flex-1">
                                <flux:select 
                                    wire:model.live="items.{{ $index }}.distributions.{{ $distIndex }}.warehouse_id" 
                                    x-model="warehouseId" 
                                    @change="$nextTick(() => { setTimeout(() => { document.getElementById('qty-input-{{ $index }}-{{ $distIndex }}')?.focus(); }, 50) })"
                                    class="w-full text-sm" 
                                    :disabled="$shouldDisable"
                                >
                                    <option value="" disabled selected>Pilih Gudang...</option>
                                    @foreach($warehouses_list as $wh)
                                        <option value="{{ $wh->id }}" @disabled(in_array($wh->id, $whIds) && $wh->id != $dist['warehouse_id'])>
                                            {{ $wh->name }} {{ in_array($wh->id, $whIds) && $wh->id != $dist['warehouse_id'] ? '(Sudah dipilih)' : '' }}
                                        </option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div class="w-24 shrink-0" x-show="warehouseId" style="display: none;" x-transition.opacity.duration.300ms>
                                <flux:input 
                                    id="qty-input-{{ $index }}-{{ $distIndex }}"
                                    wire:model.live="items.{{ $index }}.distributions.{{ $distIndex }}.qty" 
                                    type="number" 
                                    min="0"
                                    class="w-24 text-center text-sm"
                                    :disabled="$shouldDisable"
                                />
                            </div>
                            @if(count($item['distributions']) > 1)
                                <div class="pb-1">
                                    <flux:button variant="danger" size="sm" icon="trash" wire:click="removeDistribution({{ $index }}, {{ $distIndex }})" class="!px-2" />
                                </div>
                            @endif
                        </div>
                    @endforeach
                    
                    @if($currentTotal < $item['remaining'] && count($item['distributions']) < count($warehouses_list))
                        <div>
                            <flux:button variant="subtle" size="sm" icon="plus" wire:click="addDistribution({{ $index }})" class="w-full text-xs">Tambah Lokasi Gudang</flux:button>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-8 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl text-center text-zinc-500">
                Semua barang pada PO ini sudah diterima sepenuhnya.
            </div>
        @endforelse
    </div>

    <flux:textarea wire:model="notes" label="Catatan Penerimaan" placeholder="Tuliskan jika ada barang cacat atau kurang..." />

    @php
        $globalError = false;
        $globalTotal = 0;
        foreach($items as $item) {
            $ct = 0;
            $whIds = [];
            
            foreach ($item['distributions'] as $dist) {
                $qty = (int)($dist['qty'] ?? 0);
                $hasWh = !empty($dist['warehouse_id']);
                
                // Jika murni draf kosong (belum disentuh), abaikan saja.
                // Ini wajar jika pengguna hanya ingin menerima sebagian barang di PO.
                if (!$hasWh && $qty <= 0) {
                    continue; 
                }
                
                // Jika nanggung (gudang diisi tapi qty 0, atau qty diisi tapi gudang kosong), BLOCK!
                if ($hasWh && $qty <= 0) {
                    $globalError = true;
                }
                if (!$hasWh && $qty > 0) {
                    $globalError = true;
                }
                
                // Jika valid, hitung totalnya
                if ($hasWh && $qty > 0) {
                    $whIds[] = $dist['warehouse_id'];
                    $ct += $qty;
                }
            }
            
            $globalTotal += $ct;
            
            if ($ct > $item['remaining'] || count($whIds) !== count(array_unique($whIds))) {
                $globalError = true;
            }
        }
        $canSave = !$globalError && $globalTotal > 0;
    @endphp

    <div class="flex justify-end gap-2 mt-6">
        <flux:button wire:click="$set('show', false)" variant="ghost">Batal</flux:button>
        <flux:button wire:click="save" variant="primary" :disabled="!$canSave">Simpan Penerimaan</flux:button>
    </div>
    @endif
</flux:modal>
