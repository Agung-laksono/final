<?php
use function Livewire\Volt\{state, on, computed};
use Modules\Production\Models\ProductionRecipe;
use Modules\Production\Models\ProductionRecipeItem;
use Modules\Inventory\Models\Item;

state([
    'show' => false,
    'recipeId' => null,
    'item_id' => '',
    'materials' => [], // ['item_id' => '', 'qty' => 1]
]);

$availableProducts = computed(function () {
    // Only fetch items that don't have a recipe yet, OR the current one being edited
    // But honestly, they might want a recipe for any type. Let's fetch 'produk jadi' and 'barang setengah jadi'
    return Item::whereHas('type', function ($q) {
            $q->whereIn('name', ['produk jadi', 'barang setengah jadi', 'Produk Jadi', 'Barang Setengah Jadi']);
        })
        ->orderBy('name')
        ->get();
});

$availableMaterials = computed(function () {
    // Materials can be 'bahan baku utama', 'bahan baku penolong', or even 'barang setengah jadi'
    return Item::orderBy('name')->get(); 
});

on(['open-recipe-modal' => function ($params = null) {
    $this->reset(['recipeId', 'item_id', 'materials']);
    
    if (isset($params['recipeId'])) {
        $recipe = ProductionRecipe::with('items')->find($params['recipeId']);
        if ($recipe) {
            $this->recipeId = $recipe->id;
            $this->item_id = $recipe->item_id;
            foreach ($recipe->items as $bom) {
                $this->materials[] = [
                    'item_id' => $bom->item_id,
                    'qty' => $bom->qty
                ];
            }
        }
    } else {
        $this->addMaterial(); // Add one empty row
    }
    
    $this->show = true;
}]);

$addMaterial = function () {
    $this->materials[] = ['item_id' => '', 'qty' => 1];
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
        'materials.*.qty' => 'required|numeric|min:0.01',
    ], [
        'item_id.required' => 'Pilih produk jadi terlebih dahulu.',
        'materials.required' => 'Minimal harus ada 1 bahan baku.',
        'materials.*.item_id.required' => 'Bahan baku tidak boleh kosong.',
        'materials.*.qty.min' => 'Kuantitas minimal 0.01.'
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
?>

<flux:modal wire:model="show" class="md:w-[600px] max-w-full">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $recipeId ? 'Edit Resep Produksi' : 'Buat Resep Baru' }}</flux:heading>
            <flux:subheading>Tentukan produk yang akan dirakit beserta komposisi bahan-bahannya.</flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:select wire:model="item_id" label="Pilih Produk Jadi (Hasil Rakitan)" placeholder="Pilih Produk" :disabled="$recipeId !== null" searchable>
                @foreach($this->availableProducts as $prod)
                    <flux:select.option value="{{ $prod->id }}">{{ $prod->code }} - {{ $prod->name }}</flux:select.option>
                @endforeach
            </flux:select>
            
            @error('item_id')
                <div class="text-sm text-red-500">{{ $message }}</div>
            @enderror

            <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <div class="flex items-center justify-between mb-3">
                    <flux:heading size="md">Komposisi Bahan Baku (BOM)</flux:heading>
                    <flux:button size="sm" variant="subtle" icon="plus" wire:click="addMaterial">Tambah Bahan</flux:button>
                </div>
                
                @if(count($materials) == 0)
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-center text-sm text-zinc-500 border border-zinc-200 dark:border-zinc-700">
                        Belum ada bahan. Klik Tambah Bahan.
                    </div>
                @endif
                
                <div class="space-y-3">
                    @foreach($materials as $index => $mat)
                        <div class="flex items-start gap-2 bg-zinc-50 dark:bg-zinc-800/30 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <div class="flex-1 space-y-3">
                                <flux:select wire:model="materials.{{ $index }}.item_id" placeholder="Pilih Bahan Baku" searchable>
                                    @foreach($this->availableMaterials as $availMat)
                                        <flux:select.option value="{{ $availMat->id }}">{{ $availMat->code }} - {{ $availMat->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @error('materials.'.$index.'.item_id') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div class="w-24 shrink-0 space-y-3">
                                <flux:input type="number" wire:model="materials.{{ $index }}.qty" step="0.01" min="0.01" />
                                @error('materials.'.$index.'.qty') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div class="pt-1">
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
