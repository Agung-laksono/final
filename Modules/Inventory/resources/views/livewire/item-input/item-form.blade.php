<?php

use function Livewire\Volt\{state, rules, on, with};
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Type;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\SubCategory;
use Illuminate\Support\Str;
use Flux\Flux;

state([
    'item_id' => null,
    'code' => '',
    'name' => '',
    'image' => null, // Tempat menampung Base64 dari Cropper
    'unit_id' => '',
    'type_id' => '',
    'category_id' => '',
    'sub_category_id' => '',
    'purchase_price' => 0,
    'selling_price' => 0,
    'is_active' => true,
    'requires_label' => false,
    
    // Lists for dropdowns
    'units' => fn () => Unit::orderBy('name')->get(),
    'types' => fn () => Type::orderBy('name')->get(),
    'categories' => fn () => Category::orderBy('name')->get(),
    // subcategories dependent on category_id
    'subcategories' => []
]);

rules([
    'code' => 'required|string|unique:items,code', // Note: needs ignore rule for edit mode
    'name' => 'required|string|max:255',
    'unit_id' => 'nullable|exists:units,id',
    'type_id' => 'nullable|exists:types,id',
    'category_id' => 'nullable|exists:categories,id',
    'sub_category_id' => 'nullable|exists:sub_categories,id',
    'purchase_price' => 'required|numeric|min:0',
    'selling_price' => 'required|numeric|min:0',
    'is_active' => 'boolean',
    'requires_label' => 'boolean',
]);

// Hook untuk merespon ketika category_id diubah oleh user (Livewire hook)
$updatedCategoryId = function ($value) {
    if ($value) {
        $this->subcategories = SubCategory::where('category_id', $value)->orderBy('name')->get();
    } else {
        $this->subcategories = [];
    }
    $this->sub_category_id = ''; // Reset pilihan sub kategori
};

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
        // Jika ada gambar, tampilkan previewnya dengan mengambil URL dari Storage
        $this->image = $item->image ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->image) : null;
        $this->unit_id = $item->unit_id;
        $this->type_id = $item->type_id;
        $this->category_id = $item->category_id;
        
        if ($this->category_id) {
            $this->subcategories = SubCategory::where('category_id', $this->category_id)->orderBy('name')->get();
        }
        
        $this->sub_category_id = $item->sub_category_id;
        $this->purchase_price = $item->purchase_price;
        $this->selling_price = $item->selling_price;
        $this->is_active = $item->is_active;
        $this->requires_label = $item->requires_label;
    } else {
        $this->item_id = null;
        $this->code = 'ITM-' . strtoupper(Str::random(6)); // Auto-generate kode random
        $this->name = '';
        $this->image = null;
        $this->unit_id = '';
        $this->type_id = '';
        $this->category_id = '';
        $this->subcategories = [];
        $this->sub_category_id = '';
        $this->purchase_price = 0;
        $this->selling_price = 0;
        $this->is_active = true;
        $this->requires_label = false;
    }
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
            // Panggil logic update subcategory
            $this->subcategories = SubCategory::where('category_id', $id)->orderBy('name')->get();
            $this->sub_category_id = '';
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
    $rules = $this->rules();
    // Jika sedang edit, kecualikan kode barang saat ini dari validasi unique
    if ($this->item_id) {
        $rules['code'] = 'required|string|unique:items,code,' . $this->item_id;
    }
    $this->validate($rules);

    $data = [
        'code' => $this->code,
        'name' => $this->name,
        'unit_id' => $this->unit_id ?: null,
        'type_id' => $this->type_id ?: null,
        'category_id' => $this->category_id ?: null,
        'sub_category_id' => $this->sub_category_id ?: null,
        'purchase_price' => $this->purchase_price,
        'selling_price' => $this->selling_price,
        'is_active' => $this->is_active,
        'requires_label' => $this->requires_label,
        'user_id' => auth()->id(), // Mencatat user yang memasukkan
    ];

    // Proses konversi gambar jika ada perubahan (Base64 dari Cropperjs)
    if ($this->image && str_starts_with($this->image, 'data:image')) {
        // Ambil ekstensi (walau cropper sudah kita set fix ke webp, kita parse saja)
        preg_match('/data:image\/(.*?);/', $this->image, $match);
        $extension = $match[1] ?? 'webp';
        
        // Hapus header base64
        $image_parts = explode(";base64,", $this->image);
        $image_base64 = base64_decode($image_parts[1]);
        
        // Buat nama file unik
        $fileName = 'items/' . uniqid() . '.' . $extension;
        
        // Simpan ke storage/app/public/items
        \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $image_base64);
        
        $data['image'] = $fileName;
    } else if ($this->image === null) {
        // Jika user sengaja menghapus gambar
        $data['image'] = null;
    }

    if ($this->item_id) {
        Item::findOrFail($this->item_id)->update($data);
    } else {
        Item::create($data);
    }

    $this->dispatch('item-saved'); // Beritahu tabel utama agar me-refresh sekaligus menutup modal Alpine
    Flux::modal('item-modal')->close();
};

?>

<div>
    {{-- Tombol pemicu modal --}}
    <div class="mb-6">
        <flux:button wire:click="openModal" variant="primary" icon="plus">Tambah Barang Baru</flux:button>
    </div>

    <flux:modal name="item-modal" class="md:w-[800px] space-y-6" x-on:item-saved.window="$modal('item-modal').close()">
        <div>
            <flux:heading size="lg">{{ $item_id ? 'Edit Barang' : 'Tambah Barang Baru' }}</flux:heading>
            <flux:subheading>Isi rincian informasi barang ke dalam database inventori.</flux:subheading>
        </div>
        
        <form wire:submit="save" class="space-y-6">
            
            {{-- Komponen Upload & Crop Gambar Global --}}
            <x-image-cropper wire:model="image" :preview="$image" label="Foto Barang (Opsional)" ratio="1" />

            {{-- Informasi Dasar --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="code" label="Kode Barang" placeholder="Contoh: ITM-001" required />
                <flux:input wire:model="name" label="Nama Barang" placeholder="Contoh: Baterai ABC" required />
            </div>

            <flux:separator text="Klasifikasi" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Type --}}
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:select wire:model="type_id" label="Tipe Barang" placeholder="Pilih Tipe...">
                            @foreach($types as $type)
                                <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button variant="ghost" icon="plus" wire:click="$dispatch('open-type-modal')" tooltip="Tambah Tipe Baru" />
                </div>

                {{-- Unit --}}
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:select wire:model="unit_id" label="Satuan (Unit)" placeholder="Pilih Satuan...">
                            @foreach($units as $unit)
                                <flux:select.option value="{{ $unit->id }}">{{ $unit->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button variant="ghost" icon="plus" wire:click="$dispatch('open-unit-modal')" tooltip="Tambah Satuan Baru" />
                </div>

                {{-- Category --}}
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:select wire:model.live="category_id" label="Kategori Induk" placeholder="Pilih Kategori...">
                            @foreach($categories as $category)
                                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button variant="ghost" icon="plus" wire:click="$dispatch('open-category-modal')" tooltip="Tambah Kategori Baru" />
                </div>

                {{-- Sub Category --}}
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:select wire:model="sub_category_id" label="Sub Kategori" placeholder="Pilih Sub Kategori..." :disabled="empty($subcategories)">
                            @foreach($subcategories as $sub)
                                <flux:select.option value="{{ $sub->id }}">{{ $sub->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button variant="ghost" icon="plus" wire:click="$dispatch('open-subcategory-modal')" tooltip="Tambah Sub Kategori Baru" :disabled="!$category_id" />
                </div>
            </div>

            <flux:separator text="Harga & Pengaturan" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input type="number" wire:model="purchase_price" label="Harga Beli Standar (Rp)" placeholder="0" />
                <flux:input type="number" wire:model="selling_price" label="Harga Jual Standar (Rp)" placeholder="0" />
            </div>

            <div class="flex flex-col gap-3 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                <flux:switch wire:model="is_active" label="Barang Aktif" description="Barang dapat digunakan dalam seluruh transaksi inventori." />
                <flux:switch wire:model="requires_label" label="Pelacakan Serial/Label" description="Wajibkan barang ini dilacak secara individu dengan nomor seri unik per fisik barang." />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan Barang</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
