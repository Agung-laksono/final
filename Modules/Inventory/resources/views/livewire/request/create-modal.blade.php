<?php

use function Livewire\Volt\{state, on, computed};
use Modules\Inventory\Models\InventoryRequest;
use Modules\Inventory\Models\Item;
use Illuminate\Support\Facades\Storage;

state([
    'show' => false,
    'items' => [], // Format: ['item_id' => item_id, 'name' => name, 'code' => code, 'image' => image, 'unit' => unit, 'qty' => 1, 'notes' => '']
]);

on(['open-create-request-modal' => function () {
    $this->reset(['items']);
    $this->items = [];
    $this->dispatch('update-request-items', items: $this->items);
    $this->show = true;
}]);

$addItem = function ($id) {
    // Cek apakah item sudah ada di keranjang
    $exists = collect($this->items)->firstWhere('item_id', $id);
    if ($exists) {
        $this->items = collect($this->items)->map(function ($item) use ($id) {
            if ($item['item_id'] == $id) {
                $item['qty']++;
            }
            return $item;
        })->toArray();
        $this->dispatch('update-request-items', items: $this->items);
        return;
    }

    $item = Item::with('unit')->find($id);
    if ($item) {
        $this->items[] = [
            'item_id' => $item->id,
            'name' => $item->alias ? $item->alias . ' - ' . $item->name : $item->name,
            'code' => $item->code,
            'image' => $item->image,
            'unit' => $item->unit ? $item->unit->name : 'unit',
            'qty' => 1,
            'notes' => '',
        ];
        $this->dispatch('update-request-items', items: $this->items);
    }
};

$removeItem = function ($index) {
    unset($this->items[$index]);
    $this->items = array_values($this->items); // reindex
    $this->dispatch('update-request-items', items: $this->items);
};

$save = function () {
    $this->validate([
        'items' => 'required|array|min:1',
        'items.*.item_id' => 'required|exists:items,id',
        'items.*.qty' => 'required|numeric|min:1',
        'items.*.notes' => 'nullable|string',
    ], [
        'items.required' => 'Keranjang permintaan tidak boleh kosong. Pilih minimal 1 barang dari galeri.',
    ]);

    $lastRequest = InventoryRequest::where('source_type', 'manual')
        ->where('reference_number', 'like', 'REQ-M-%')
        ->orderBy('id', 'desc')
        ->first();
        
    $nextNumber = 1;
    if ($lastRequest) {
        $lastNumber = (int) str_replace('REQ-M-', '', $lastRequest->reference_number);
        $nextNumber = $lastNumber + 1;
    }

    foreach ($this->items as $cartItem) {
        $referenceNumber = 'REQ-M-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        InventoryRequest::create([
            'item_id' => $cartItem['item_id'],
            'source_type' => 'manual',
            'reference_number' => $referenceNumber,
            'requested_qty' => $cartItem['qty'],
            'notes' => $cartItem['notes'],
            'status' => 'review',
        ]);
        
        $nextNumber++; // Increment untuk barang selanjutnya
    }

    $this->dispatch('status-updated'); // Memanggil event agar kanban ter-refresh
    \App\Events\KanbanUpdated::safeDispatch('inventory_request');
    
    // Clear keranjang
    $this->items = [];
    $this->dispatch('update-request-items', items: $this->items);
    
    $this->show = false;
    \Flux::toast('Permintaan barang berhasil dibuat dan langsung masuk antrean Peninjauan!', 'success');
};

?>

<div>
    <flux:modal wire:model="show" class="md:w-[650px]" wire:ignore.self>
        <div class="border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-5">
            <flux:heading size="lg">Buat Permintaan Barang (Manual)</flux:heading>
            <flux:subheading>Isi form ini untuk mengajukan permintaan barang ke Gudang. Anda dapat memilih beberapa barang sekaligus.</flux:subheading>
        </div>

        {{-- Menggunakan event listener di tingkat root form agar galeri bisa diakses berulang --}}
        <form wire:submit="save" class="space-y-5" @item-selected.window="$wire.addItem($event.detail.item.item_id)">
            
            {{-- Tombol Galeri --}}
            <div class="flex items-center justify-between">
                <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Daftar Barang Diminta <span class="text-zinc-400 font-normal">({{ count($items) }} barang)</span>
                </div>
                <flux:button size="sm" variant="primary" class="shrink-0" x-data="{ loading: false }" x-on:click="loading = true; setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading" icon="squares-plus">Tambah dari Galeri</flux:button>
            </div>

            @error('items')
                <div class="text-red-500 text-sm mt-1 bg-red-50 dark:bg-red-900/30 p-2 rounded-lg border border-red-200 dark:border-red-800">
                    {{ $message }}
                </div>
            @enderror

            {{-- Keranjang / Daftar Barang --}}
            <div class="bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-800 p-2 max-h-[50vh] overflow-y-auto space-y-3 custom-scrollbar">
                @forelse($items as $index => $item)
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 shadow-sm relative group flex flex-col gap-3">
                        
                        {{-- Tombol Hapus Pojok Kanan Atas --}}
                        <div class="absolute top-2 right-2">
                            <flux:button variant="subtle" size="sm" icon="trash" wire:click="removeItem({{ $index }})" class="text-zinc-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 !px-2 !h-8 transition-colors rounded-full" tooltip="Hapus barang ini" />
                        </div>

                        {{-- Header Baris: Info Barang --}}
                        <div class="flex gap-3 pr-10">
                            {{-- Foto --}}
                            <div class="w-14 h-14 shrink-0 bg-zinc-100 dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden flex items-center justify-center">
                                @if($item['image'])
                                    <img src="{{ Storage::url($item['image']) }}" class="w-full h-full object-cover">
                                @else
                                    <flux:icon.cube class="w-6 h-6 text-zinc-400" />
                                @endif
                            </div>
                            
                            {{-- Detail --}}
                            <div class="flex-1 flex flex-col justify-center">
                                <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-sm leading-tight">{{ $item['name'] }}</h4>
                                <div class="text-[11px] font-mono text-zinc-500 mt-0.5">{{ $item['code'] }}</div>
                            </div>
                        </div>

                        {{-- Form Inputs (Qty & Notes) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                            <div class="sm:col-span-4">
                                <flux:field>
                                    <flux:label class="!text-xs">Jumlah</flux:label>
                                    <div class="flex items-center">
                                        <flux:input type="number" inputmode="numeric" pattern="[0-9]*" wire:model="items.{{ $index }}.qty" min="1" required class="rounded-r-none text-center" />
                                        <div class="bg-zinc-100 dark:bg-zinc-800 border border-l-0 border-zinc-200 dark:border-zinc-700 px-3 py-2 rounded-r-lg text-sm text-zinc-600 dark:text-zinc-400 font-medium whitespace-nowrap">
                                            {{ $item['unit'] }}
                                        </div>
                                    </div>
                                    <flux:error name="items.{{ $index }}.qty" />
                                </flux:field>
                            </div>
                            <div class="sm:col-span-8">
                                <flux:field>
                                    <flux:label class="!text-xs">Catatan / Alasan</flux:label>
                                    <flux:input type="text" wire:model="items.{{ $index }}.notes" placeholder="Opsional (Misal: Butuh cepat...)" />
                                    <flux:error name="items.{{ $index }}.notes" />
                                </flux:field>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10">
                        <div class="w-12 h-12 bg-zinc-100 dark:bg-zinc-800 rounded-full flex items-center justify-center mx-auto mb-3">
                            <flux:icon.shopping-cart class="w-6 h-6 text-zinc-400" />
                        </div>
                        <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Belum ada barang</h4>
                        <p class="text-xs text-zinc-500 mt-1">Klik tombol "Tambah dari Galeri" untuk memulai.</p>
                    </div>
                @endforelse
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
                <flux:button variant="primary" type="submit" icon="paper-airplane" x-data="{ submitting: false }" x-on:click="submitting = true; $wire.save().then(() => submitting = false)" x-bind:disabled="submitting">
                    <span x-show="!submitting">Ajukan Permintaan</span>
                    <span x-show="submitting">Memproses...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
