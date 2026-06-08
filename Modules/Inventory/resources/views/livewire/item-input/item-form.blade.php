<?php

use function Livewire\Volt\{state, rules, on, uses, updated};
use Livewire\WithFileUploads;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Type;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\SubCategory;
use Illuminate\Support\Str;
use Flux\Flux;

uses([WithFileUploads::class]);

state([
    'item_id' => null,
    'code' => '',
    'name' => '',
    'description' => '',
    'image' => null, // Tempat menampung Base64 dari Cropper
    'unit_id' => '',
    'type_id' => '',
    'category_id' => '',
    'sub_category_id' => '',
    'purchase_price' => null,
    'selling_price' => null,
    'min_stock' => null,
    'max_stock' => null,
    'is_active' => true,
    'requires_label' => false,
    'show' => fn () => request()->routeIs('inventory-settings'),
    
    // Lists for dropdowns
    'units' => fn () => Unit::orderBy('name')->get(),
    'types' => fn () => Type::orderBy('name')->get(),
    'categories' => fn () => Category::orderBy('name')->get(),
    // subcategories dependent on category_id
    'subcategories' => [],
    'items' => fn () => Item::with(['category', 'unit', 'type'])->latest()->get(),
]);

rules([
    'image' => 'required',
    'name' => 'required|string|max:255',
    'description' => 'nullable|string',
    'unit_id' => 'required|exists:units,id',
    'type_id' => 'required|exists:types,id',
    'category_id' => 'required|exists:categories,id',
    'sub_category_id' => 'nullable|exists:sub_categories,id',
    'purchase_price' => 'required|numeric|min:0',
    'selling_price' => 'required|numeric|min:0|gte:purchase_price',
    'min_stock' => 'required|integer|min:0',
    'max_stock' => 'required|integer|min:0|gte:min_stock',
    'is_active' => 'boolean',
    'requires_label' => 'boolean',
]);

// Hook untuk bereaksi saat kategori berubah
updated([
    'category_id' => function ($value) {
        if ($value) {
            $this->subcategories = SubCategory::where('category_id', $value)->orderBy('name')->get();
        } else {
            $this->subcategories = [];
        }
        $this->sub_category_id = ''; // Reset pilihan sub kategori
        
        // Beritahu frontend untuk mengupdate opsi subkategori
        $this->dispatch('subcategory-updated', options: $this->subcategories);
    }
]);

$openModal = function ($id = null) {
    $this->resetValidation();
    // Refresh dropdowns
    $this->units = Unit::orderBy('name')->get();
    $this->types = Type::orderBy('name')->get();
    $this->categories = Category::orderBy('name')->get();
    
    if ($id) {
        $item = Item::findOrFail($id);
        $this->item_id = $item->id;
        $this->code = $item->code;
        $this->name = $item->name;
        $this->description = $item->description;
        // Jika ada gambar, ambil path aslinya saja (tanpa full URL) karena view sudah menggunakan asset()
        $this->image = $item->image;
        $this->unit_id = $item->unit_id;
        $this->type_id = $item->type_id;
        $this->category_id = $item->category_id;
        
        if ($this->category_id) {
            $this->subcategories = SubCategory::where('category_id', $this->category_id)->orderBy('name')->get();
        }
        
        $this->sub_category_id = $item->sub_category_id;
        $this->purchase_price = $item->purchase_price;
        $this->selling_price = $item->selling_price;
        $this->min_stock = $item->min_stock;
        $this->max_stock = $item->max_stock;
        $this->is_active = $item->is_active;
        $this->requires_label = $item->requires_label;
    } else {
        $this->item_id = null;
        $this->code = ''; // Kode akan di-generate otomatis saat disave
        $this->name = '';
        $this->description = '';
        $this->image = null;
        $this->unit_id = '';
        $this->type_id = '';
        $this->category_id = '';
        $this->subcategories = [];
        $this->sub_category_id = '';
        $this->purchase_price = null;
        $this->selling_price = null;
        $this->min_stock = null;
        $this->max_stock = null;
        $this->is_active = true;
        $this->requires_label = false;
    }
    
    // Sinkronisasi data ke Alpine dropdown setelah data backend disiapkan
    $this->dispatch('unit-updated', options: $this->units);
    $this->dispatch('type-updated', options: $this->types);
    $this->dispatch('category-updated', options: $this->categories);
    $this->dispatch('subcategory-updated', options: $this->subcategories);

    $this->dispatch('item-modal-loaded');
    Flux::modal('item-modal')->show();
};

on(['open-item-modal' => function ($id = null) {
    $this->openModal($id);
}]);

// Listeners ajaib: Menerima update dari form modal Master Data beserta ID barunya
on([
    'unit-updated' => function ($id = null) { 
        $this->units = Unit::orderBy('name')->get(); 
        if ($id) $this->unit_id = $id;
    },
    'type-updated' => function ($id = null) { 
        $this->types = Type::orderBy('name')->get(); 
        if ($id) $this->type_id = $id;
    },
    'category-updated' => function ($id = null) { 
        $this->categories = Category::orderBy('name')->get(); 
        if ($id) {
            $this->category_id = $id;
            $this->subcategories = SubCategory::where('category_id', $id)->orderBy('name')->get();
            $this->sub_category_id = '';
            $this->dispatch('subcategory-updated', options: $this->subcategories);
        }
    },
    'subcategory-updated' => function ($id = null) { 
        if ($this->category_id) {
            $this->subcategories = SubCategory::where('category_id', $this->category_id)->orderBy('name')->get();
            if ($id) $this->sub_category_id = $id;
        }
    }
]);

$save = function () {
    if (! $this->item_id) {
        // Auto-generate kode berurutan (contoh: ITM-0001)
        $lastItem = Item::orderBy('id', 'desc')->first();
        $nextId = $lastItem ? $lastItem->id + 1 : 1;
        $this->code = 'ITM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }

    $validated = $this->validate();
    $validated['code'] = $this->code; // Masukkan kode yang sudah di-generate atau yang sudah ada
    $validated['user_id'] = auth()->id();
    
    // Pastikan string kosong pada dropdown diubah menjadi null
    $validated['unit_id'] = $validated['unit_id'] ?: null;
    $validated['type_id'] = $validated['type_id'] ?: null;
    $validated['category_id'] = $validated['category_id'] ?: null;
    $validated['sub_category_id'] = $validated['sub_category_id'] ?: null;

    // Jika ada file gambar yang diupload via Alpine JS (dalam bentuk Base64)
    if (is_string($this->image) && str_starts_with($this->image, 'data:image/webp;base64,')) {
        // Ekstrak data base64 murni tanpa prefix
        $base64Image = substr($this->image, strpos($this->image, ',') + 1);
        $imageData = base64_decode($base64Image);
        
        $filename = 'items/' . uniqid() . '.webp';
        \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $imageData);
        
        $validated['image'] = $filename;
    } elseif ($this->image === null) {
        $validated['image'] = null;
    } else {
        // Jika string biasa (URL preview saat edit), jangan diubah
        unset($validated['image']);
    }

    if ($this->item_id) {
        $item = Item::find($this->item_id);
        
        // Bersihkan gambar lama dari storage jika user mengupload gambar baru, atau jika gambar dihapus
        if ($item->image && (array_key_exists('image', $validated) || $this->image === null)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($item->image);
        }
        
        $item->update($validated);
        $actionType = 'diperbarui';
    } else {
        Item::create($validated);
        $actionType = 'ditambahkan';
    }

    $this->items = Item::with(['category', 'unit', 'type'])->latest()->get();
    Flux::modal('item-modal')->close();
    $this->dispatch('item-saved'); // Beritahu tabel utama agar me-refresh
    
    // Beritahu user lain secara realtime via Reverb
    \App\Events\InventoryUpdated::safeDispatch("Data barang {$validated['code']} berhasil {$actionType}");
};

$delete = function (Item $item) {
    // Bersihkan file gambar dari storage sebelum datanya dihapus dari database
    if ($item->image) {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($item->image);
    }
    
    $item->delete();
    $this->items = Item::with(['category', 'unit', 'type'])->latest()->get();
    $this->dispatch('item-deleted');
    
    // Beritahu user lain secara realtime via Reverb
    \App\Events\InventoryUpdated::safeDispatch("Data barang {$item->code} berhasil dihapus");
};

?>

<div> @if ($show)
    <div x-on:trigger-add-subcategory.window="$wire.dispatch('open-subcategory-modal', { category_id: $wire.category_id })"></div>

    <div class="flex justify-between items-center mb-6">
        <flux:heading size="lg">Pengelolaan Barang</flux:heading>
        <flux:button wire:click="openModal" variant="primary" icon="plus">Tambah Barang Baru</flux:button>
    </div>

    {{-- Tabel Daftar Barang --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden mb-6">
        <flux:table class="pl-5">
            <flux:table.columns>
                <flux:table.column>Info Barang</flux:table.column>
                <flux:table.column>Klasifikasi</flux:table.column>
                <flux:table.column>Batas Stok</flux:table.column>
                <flux:table.column>Harga Dasar</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($items as $i)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                @if($i->image)
                                    <img src="{{ asset('storage/' . $i->image) }}" class="w-auto h-10 rounded-lg  ring-1 ring-zinc-200 dark:ring-zinc-700">
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-zinc-400">
                                        <flux:icon.photo class="w-5 h-5" />
                                    </div>
                                @endif
                                <div class="flex flex-col">
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $i->name }}</span>
                                    <span class="text-[11px] font-mono text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded-md mt-1 w-fit">{{ $i->code }}</span>
                                </div>
                            </div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex flex-col gap-1.5">
                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $i->category?->name ?? '-' }}
                                </span>
                                <div class="flex gap-1">
                                    <flux:badge size="sm" color="zinc" variant="outline">{{ $i->type?->name ?? '-' }}</flux:badge>
                                    <flux:badge size="sm" color="zinc">{{ $i->unit?->name ?? '-' }}</flux:badge>
                                </div>
                            </div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-1.5">
                                <div class="flex flex-col items-center justify-center bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 px-2 py-1 rounded border border-rose-100 dark:border-rose-500/20 min-w-[40px]">
                                    <span class="text-[9px] font-bold uppercase tracking-widest opacity-80 mb-0.5">Min</span>
                                    <span class="text-xs font-bold">{{ $i->min_stock }}</span>
                                </div>
                                <span class="text-zinc-300 dark:text-zinc-600">-</span>
                                <div class="flex flex-col items-center justify-center bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 px-2 py-1 rounded border border-blue-100 dark:border-blue-500/20 min-w-[40px]">
                                    <span class="text-[9px] font-bold uppercase tracking-widest opacity-80 mb-0.5">Max</span>
                                    <span class="text-xs font-bold">{{ $i->max_stock }}</span>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-col gap-1">
                                <span class="text-[11px] text-zinc-500">Beli: Rp {{ number_format($i->purchase_price, 0, ',', '.') }}</span>
                                <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($i->selling_price, 0, ',', '.') }}</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-col gap-1.5 items-start">
                                @if($i->is_active)
                                    <flux:badge color="green" size="sm" icon="check-circle">Aktif</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Non-aktif</flux:badge>
                                @endif
                                
                                @if($i->requires_label)
                                    <flux:badge color="orange" size="sm" icon="qr-code">SN</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex gap-1">
                                <flux:button wire:click="openModal({{ $i->id }})" variant="ghost" size="sm" icon="pencil" class="text-blue-500 hover:text-blue-600" />
                                <flux:button wire:click="delete({{ $i->id }})" wire:confirm="Yakin menghapus barang {{ $i->name }}?" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-600" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="text-center py-10 text-zinc-500">
                                <flux:icon.cube class="w-12 h-12 mx-auto mb-3 opacity-20" />
                                <p>Belum ada barang yang ditambahkan.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    @endif

    <flux:modal name="item-modal" class="md:max-w-4xl space-y-6 px-3">
        <div>
            <flux:heading size="lg">{{ $item_id ? 'Edit Barang' : 'Tambah Barang Baru' }}</flux:heading>
        </div>
        
        <form wire:submit="save" class="flex flex-col h-full">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                
                {{-- KOLOM KIRI: Foto & Nama Dasar --}}
                <div class="space-y-6">
                    
                    {{-- Area Foto (Dibatasi proporsional) --}}
                    <div class="w-full flex flex-col items-center justify-center pt-2 pb-2">
                        <div class="w-56"> {{-- Lebar fix 224px agar tinggi (aspect-square) tidak terlalu makan tempat --}}
                            <x-image-cropper wire:model="image" :image="$image" accept="image/*" />
                        </div>
                        <span class="text-xs text-zinc-500 mt-3 font-medium">Ganti Foto Utama</span>
                    </div>

                    {{-- Nama Barang --}}
                    <div>
                        <flux:input wire:model="name" label="Nama Barang" placeholder="Contoh: Kursi kamasutra" required />
                    </div>

                    {{-- Deskripsi --}}
                    <div>
                        <flux:textarea wire:model="description" label="Deskripsi" placeholder="Keterangan tambahan tentang barang ini (opsional)" rows="3" />
                    </div>

                    

                </div>

                {{-- KOLOM KANAN: Detail Teknis, Harga, & Stok --}}
                <div class="space-y-5">
                    
                    {{-- Klasifikasi --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Satuan --}}
                        <x-addable-select wire:model.live="unit_id" options-prop="units" label="Satuan" :options="$units" add-new-event="open-unit-modal" required />
                        
                        {{-- Tipe Barang --}}
                        <x-addable-select wire:model.live="type_id" options-prop="types" label="Tipe Barang" placeholder="-- Pilih Tipe --" :options="$types" add-new-event="open-type-modal" required />

                        {{-- Kategori Utama --}}
                        <x-addable-select wire:model.live="category_id" options-prop="categories" label="Kategori Utama" placeholder="-- Pilih Kategori --" :options="$categories" add-new-event="open-category-modal" required />

                        {{-- Sub Kategori --}}
                        <x-addable-select wire:model.live="sub_category_id" options-prop="subcategories" label="Sub Kategori" placeholder="-- Pilih Sub --" :options="$subcategories" add-new-event="trigger-add-subcategory" />
                    </div>

                    <flux:separator text="Harga & Stok Dasar" />

                    {{-- Harga --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-currency-input wire:model="purchase_price" label="Harga Beli" placeholder="0" required />
                        <x-currency-input wire:model="selling_price" label="Harga Jual" placeholder="0" required />
                    </div>

                    {{-- Stok --}}
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input type="number" wire:model.live="min_stock" label="Stok Min" placeholder="0" required min="0" />
                        <flux:input type="number" wire:model.live="max_stock" label="Stok Max" placeholder="0" required x-bind:min="$wire.min_stock || 0" />
                    </div>

                </div>
            </div>

            {{-- FOOTER: Switch Kiri & Tombol Kanan --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mt-8 pt-4 border-t border-zinc-200 dark:border-zinc-800 gap-4">
                <div class="flex items-center gap-6 mb-4 md:mb-0">
                    <flux:switch wire:model="is_active" label="Status Aktif" />
                    <flux:switch wire:model="requires_label" label="Cetak label/item" />
                </div>
                
                <div class="flex gap-2 w-full sm:w-auto">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">{{ $item_id ? 'Simpan Perubahan' : 'Simpan Barang' }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
