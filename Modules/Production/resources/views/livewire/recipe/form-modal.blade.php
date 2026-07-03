<?php
use function Livewire\Volt\{state, on, computed};
use Modules\Production\Models\ProductionRecipe;
use Modules\Production\Models\ProductionRecipeItem;
use Modules\Inventory\Models\Item;

state([
    'show' => false,
    'recipeId' => null,
    'item_id' => '',
    'product_name' => '', // For display
    'materials' => [], // ['item_id' => '', 'name' => '', 'code' => '', 'qty' => 1]
    'pickingMode' => null, // 'product' or numeric index for materials
]);

on(['open-recipe-modal' => function ($recipeId = null, $itemId = null, $params = null) {
    $this->recipeId = null;
    $this->item_id = '';
    $this->product_name = '';
    $this->materials = [];
    $this->pickingMode = null;
    
    // Support both named arguments (Livewire 3 behavior) and array payload (fallback)
    if (is_array($recipeId)) {
        $params = $recipeId;
        $recipeId = $params['recipeId'] ?? null;
        $itemId = $params['itemId'] ?? null;
    } elseif (is_array($params)) {
        $recipeId = $recipeId ?? $params['recipeId'] ?? null;
        $itemId = $itemId ?? $params['itemId'] ?? null;
    }
    
    if ($recipeId) {
        $recipe = ProductionRecipe::with(['items.item', 'item'])->find($recipeId);
        if ($recipe) {
            $this->recipeId = $recipe->id;
            $this->item_id = $recipe->item_id;
            $this->product_name = $recipe->item->code . ' - ' . $recipe->item->name;
            
            foreach ($recipe->items as $bom) {
                $this->materials[] = [
                    'item_id' => $bom->item_id,
                    'code' => $bom->item->code,
                    'name' => $bom->item->name,
                    'qty' => $bom->qty
                ];
            }
        }
    } elseif ($itemId) {
        $item = Item::find($itemId);
        if ($item) {
            $this->item_id = $item->id;
            $this->product_name = $item->code . ' - ' . $item->name;
        }
    } else {
        // Start empty
    }
    
    $this->show = true;
}]);

$addMaterial = function () {
    $this->materials[] = ['item_id' => '', 'code' => '', 'name' => '', 'qty' => 1];
};

$removeMaterial = function ($index) {
    unset($this->materials[$index]);
    $this->materials = array_values($this->materials); // Reindex
};

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $this->validate([
        'item_id' => 'required',
        'materials' => 'required|array|min:1',
        'materials.*.item_id' => 'required',
        'materials.*.qty' => 'required|integer|min:1',
    ], [
        'item_id.required' => 'Pilih produk jadi terlebih dahulu.',
        'materials.required' => 'Minimal harus ada 1 bahan baku.',
        'materials.*.item_id.required' => 'Bahan baku tidak boleh kosong.',
        'materials.*.qty.min' => 'Kuantitas minimal 1.',
        'materials.*.qty.integer' => 'Kuantitas harus bilangan bulat.'
    ]);

    // Check if recipe for this item already exists (if creating new)
    if (!$this->recipeId) {
        $exists = ProductionRecipe::where('item_id', $this->item_id)->exists();
        if ($exists) {
            $this->addError('item_id', 'Resep untuk produk ini sudah ada.');
            return;
        }
    }

    $recipe = ProductionRecipe::updateOrCreate(
        ['id' => $this->recipeId],
        [
            'item_id' => $this->item_id,
            'is_active' => true
        ]
    );

    // Sync BOM items
    $recipe->items()->delete();
    foreach ($this->materials as $mat) {
        $recipe->items()->create([
            'item_id' => $mat['item_id'],
            'qty' => $mat['qty']
        ]);
    }

    \Flux::toast('Resep berhasil disimpan!', variant: 'success');
    $this->dispatch('recipe-saved');
    $this->show = false;
};

$handleItemSelected = function ($itemData) {
    if ($this->pickingMode === 'product') {
        $this->item_id = $itemData['item_id'];
        $this->product_name = $itemData['code'] . ' - ' . $itemData['name'];
    } elseif (is_numeric($this->pickingMode)) {
        $index = $this->pickingMode;
        if (isset($this->materials[$index])) {
            $this->materials[$index]['item_id'] = $itemData['item_id'];
            $this->materials[$index]['code'] = $itemData['code'];
            $this->materials[$index]['name'] = $itemData['name'];
        }
    }
};
?>

<div x-data="{ isRecipeOpen: @entangle('show') }" @item-selected.window="if(isRecipeOpen) { $wire.handleItemSelected($event.detail.item); setTimeout(() => { $flux.modal('gallery-modal').close() }, 50); }">
<flux:modal wire:model="show" class="md:w-[700px] max-w-full">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $recipeId ? 'Edit Resep Produksi' : 'Buat Resep Baru' }}</flux:heading>
            <flux:subheading>Tentukan produk yang akan dirakit beserta komposisi bahan-bahannya.</flux:subheading>
        </div>

        <div class="space-y-4">
            <div>
                <flux:label>Pilih Produk Jadi (Hasil Rakitan)</flux:label>
                <div class="flex gap-2 mt-1">
                    <flux:input wire:model="product_name" readonly placeholder="Ketik atau klik tombol galeri ->" class="flex-1 bg-zinc-50" />
                    <flux:button variant="primary" class="shrink-0" x-data="{ loading: false }" x-on:click="loading = true; $wire.set('pickingMode', 'product'); setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading || $recipeId !== null" icon="squares-plus">Galeri</flux:button>
                </div>
            </div>
            
            @error('item_id')
                <div class="text-sm text-red-500">{{ $message }}</div>
            @enderror

            <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <div class="flex items-center justify-between mb-3">
                    <flux:heading size="md">Komposisi Bahan Baku (BOM)</flux:heading>
                    <flux:button size="sm" variant="subtle" icon="plus" wire:click="addMaterial">Tambah Baris</flux:button>
                </div>
                
                @if(empty($materials))
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-center text-sm text-zinc-500 border border-zinc-200 dark:border-zinc-700">
                        Belum ada bahan. Klik Tambah Baris.
                    </div>
                @endif
                
                <div class="space-y-3">
                    @foreach($materials ?? [] as $index => $mat)
                        <div class="flex items-start gap-2 bg-zinc-50 dark:bg-zinc-800/30 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <div class="flex-1 space-y-1">
                                <div class="flex gap-2">
                                    <flux:input readonly value="{{ $mat['name'] ? ($mat['code'] . ' - ' . $mat['name']) : '' }}" placeholder="Pilih bahan dari galeri..." class="flex-1 bg-white" />
                                    <flux:button variant="primary" class="shrink-0" x-data="{ loading: false }" x-on:click="loading = true; $wire.set('pickingMode', {{ $index }}); setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading" icon="squares-plus">Galeri</flux:button>
                                </div>
                                @error('materials.'.$index.'.item_id') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div class="w-28 shrink-0 space-y-1">
                                <flux:input type="number" wire:model="materials.{{ $index }}.qty" step="1" min="1" placeholder="Qty" />
                                @error('materials.'.$index.'.qty') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div class="pt-0.5">
                                <flux:button size="sm" variant="ghost" icon="trash" class="text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30" wire:click="removeMaterial({{ $index }})" tabindex="-1" />
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('materials')
                    <div class="text-sm text-red-500 mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-4">
            <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
            <flux:button variant="primary" wire:click="save">Simpan Resep</flux:button>
        </div>
    </div>
</flux:modal>
</div>
