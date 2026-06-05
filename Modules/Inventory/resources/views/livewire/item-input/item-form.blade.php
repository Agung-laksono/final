<?php

use function Livewire\Volt\{state, rules, on, uses};
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
    'image' => null, // Tempat menampung Base64 dari Cropper
    'unit_id' => '',
    'type_id' => '',
    'category_id' => '',
    'sub_category_id' => '',
    'purchase_price' => 0,
    'selling_price' => 0,
    'min_stock' => 0,
    'max_stock' => 0,
    'is_active' => true,
    'requires_label' => false,
    
    // Lists for dropdowns
    'units' => fn () => Unit::orderBy('name')->get(),
    'types' => fn () => Type::orderBy('name')->get(),
    'categories' => fn () => Category::orderBy('name')->get(),
    // subcategories dependent on category_id
    'subcategories' => [],
    'items' => fn () => Item::with(['category', 'unit', 'type'])->latest()->get(),
]);

rules([
    'name' => 'required|string|max:255',
    'unit_id' => 'required|exists:units,id',
    'type_id' => 'required|exists:types,id',
    'category_id' => 'required|exists:categories,id',
    'sub_category_id' => 'required|exists:sub_categories,id',
    'purchase_price' => 'required|numeric|min:0',
    'selling_price' => 'required|numeric|min:0',
    'min_stock' => 'required|integer|min:0',
    'max_stock' => 'required|integer|min:0',
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
        $this->image = null;
        $this->unit_id = '';
        $this->type_id = '';
        $this->category_id = '';
        $this->subcategories = [];
        $this->sub_category_id = '';
        $this->purchase_price = 0;
        $this->selling_price = 0;
        $this->min_stock = 0;
        $this->max_stock = 0;
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
    } else {
        Item::create($validated);
    }

    $this->items = Item::with(['category', 'unit', 'type'])->latest()->get();
    Flux::modal('item-modal')->close();
    $this->dispatch('item-saved'); // Beritahu tabel utama agar me-refresh
};

$delete = function (Item $item) {
    // Bersihkan file gambar dari storage sebelum datanya dihapus dari database
    if ($item->image) {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($item->image);
    }
    
    $item->delete();
    $this->items = Item::with(['category', 'unit', 'type'])->latest()->get();
    $this->dispatch('item-deleted');
};

?>

<div>
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
                                    <img src="{{ asset('storage/' . $i->image) }}" class="w-10 h-10 rounded-lg object-cover ring-1 ring-zinc-200 dark:ring-zinc-700">
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

    <flux:modal name="item-modal" class="md:w-[48rem] space-y-6">
        <div>
            <flux:heading size="lg">{{ $item_id ? 'Edit Barang' : 'Tambah Barang Baru' }}</flux:heading>
            <flux:subheading>Isi rincian informasi barang ke dalam database inventori.</flux:subheading>
        </div>
        
        <form wire:submit="save" class="space-y-6">
            {{-- Komponen Upload Gambar Interaktif dengan Kompresi (Alpine JS) --}}
            <div x-data="{
                isProcessing: false,
                originalFile: null,
                originalSize: 0,
                newSize: 0,
                maxSize: 800,
                quality: 0.8,
                
                handleFile(event) {
                    this.originalFile = event.target.files[0];
                    if (this.originalFile) {
                        this.originalSize = this.originalFile.size;
                        this.processImage();
                    }
                },
                
                processImage() {
                    if (!this.originalFile) return;
                    this.isProcessing = true;
                    
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const img = new Image();
                        img.onload = () => {
                            const canvas = document.createElement('canvas');
                            let width = img.width;
                            let height = img.height;
                            
                            if (width > height && width > this.maxSize) {
                                height *= this.maxSize / width;
                                width = this.maxSize;
                            } else if (height > this.maxSize) {
                                width *= this.maxSize / height;
                                height = this.maxSize;
                            }
                            
                            canvas.width = width;
                            canvas.height = height;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, width, height);
                            
                            // Konversi. Jika quality 1.0 (None), kita tetap pakai webp agar konsisten ringan, tapi kualitas penuh
                            const dataUrl = canvas.toDataURL('image/webp', this.quality);
                            
                            // Hitung ukuran perkiraan base64 (rumusnya: panjang string * 0.75)
                            const base64Length = dataUrl.length - (dataUrl.indexOf(',') + 1);
                            this.newSize = Math.floor(base64Length * 0.75);
                            
                            $wire.set('image', dataUrl);
                            this.isProcessing = false;
                        };
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(this.originalFile);
                },
                
                formatSize(bytes) {
                    if (bytes === 0) return '0 KB';
                    return (bytes / 1024).toFixed(1) + ' KB';
                }
            }" class="space-y-4 bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
                
                <div class="space-y-1">
                    <flux:input type="file" x-on:change="handleFile" label="Foto Barang (Otomatis Kompres & WEBP)" accept="image/*" />
                    <flux:error name="image" />
                </div>
                
                <template x-if="originalFile">
                    <div class="space-y-4 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                        {{-- Info Ukuran --}}
                        <div class="flex items-center gap-4 text-sm">
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-bold text-zinc-500">Asli</span>
                                <span class="font-semibold text-zinc-700 dark:text-zinc-300" x-text="formatSize(originalSize)"></span>
                            </div>
                            <flux:icon.arrow-right class="w-4 h-4 text-zinc-300" />
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-bold text-emerald-600">Sesudah</span>
                                <span class="font-semibold text-emerald-600" x-text="formatSize(newSize)"></span>
                            </div>
                            <span x-show="isProcessing" class="text-xs text-blue-500 font-medium flex items-center gap-1 ml-auto">
                                <flux:icon.arrow-path class="w-3 h-3 animate-spin" /> Proses...
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            {{-- Resolusi --}}
                            <div>
                                <div class="text-xs font-semibold mb-2 text-zinc-600 dark:text-zinc-400">Maks Resolusi (px)</div>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="s in [1000, 800, 600, 400]">
                                        <button type="button" 
                                            @click="maxSize = s; processImage()" 
                                            :class="maxSize === s ? 'bg-zinc-800 text-white dark:bg-white dark:text-zinc-900' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100'"
                                            class="px-2.5 py-1 text-[11px] font-bold rounded-lg transition-colors"
                                            x-text="s">
                                        </button>
                                    </template>
                                </div>
                            </div>
                            
                            {{-- Kompresi --}}
                            <div>
                                <div class="text-xs font-semibold mb-2 text-zinc-600 dark:text-zinc-400">Tingkat Kompresi</div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" @click="quality = 1.0; processImage()" :class="quality === 1.0 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100'" class="px-2.5 py-1 text-[11px] font-bold rounded-lg transition-colors">None</button>
                                    <button type="button" @click="quality = 0.8; processImage()" :class="quality === 0.8 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100'" class="px-2.5 py-1 text-[11px] font-bold rounded-lg transition-colors">Medium</button>
                                    <button type="button" @click="quality = 0.6; processImage()" :class="quality === 0.6 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100'" class="px-2.5 py-1 text-[11px] font-bold rounded-lg transition-colors">Low</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            
            {{-- Preview Gambar --}}
            @if ($image && is_string($image) && str_starts_with($image, 'data:image'))
                <div class="mt-2">
                    <img src="{{ $image }}" class="w-32 h-32 object-cover rounded-xl border border-zinc-200">
                </div>
            @elseif ($image && !is_object($image))
                <div class="mt-2">
                    <img src="{{ asset('storage/' . $image) }}" class="w-32 h-32 object-cover rounded-xl border border-zinc-200 dark:border-zinc-700">
                </div>
            @endif

            {{-- Informasi Dasar --}}
            <div>
                <flux:input wire:model="name" label="Nama Barang" placeholder="Contoh: Baterai ABC" required />
            </div>

            <flux:separator text="Klasifikasi" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:select wire:model="unit_id" label="Satuan (Unit)">
                            <flux:select.option value="">-- Pilih Satuan --</flux:select.option>
                            @foreach($units as $u)
                                <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button wire:click="$dispatch('open-unit-modal')" icon="plus" class="shrink-0" title="Tambah Satuan Baru" />
                </div>

                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:select wire:model="type_id" label="Tipe Barang">
                            <flux:select.option value="">-- Pilih Tipe --</flux:select.option>
                            @foreach($types as $t)
                                <flux:select.option value="{{ $t->id }}">{{ $t->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button wire:click="$dispatch('open-type-modal')" icon="plus" class="shrink-0" title="Tambah Tipe Baru" />
                </div>

                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:select wire:model.live="category_id" label="Kategori Utama">
                            <flux:select.option value="">-- Pilih Kategori --</flux:select.option>
                            @foreach($categories as $c)
                                <flux:select.option value="{{ $c->id }}">{{ $c->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button wire:click="$dispatch('open-category-modal')" icon="plus" class="shrink-0" title="Tambah Kategori Baru" />
                </div>

                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:select wire:model="sub_category_id" label="Sub Kategori">
                            <flux:select.option value="">-- Pilih Sub Kategori --</flux:select.option>
                            @foreach($subcategories as $sc)
                                <flux:select.option value="{{ $sc->id }}">{{ $sc->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button wire:click="$dispatch('open-subcategory-modal')" icon="plus" class="shrink-0" title="Tambah Sub Kategori Baru" />
                </div>
            </div>

            <flux:separator text="Harga & Stok Dasar" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Harga Beli dengan AlpineJS Masking --}}
                <div x-data="{ 
                    val: @entangle('purchase_price').live,
                    format(v) { 
                        if (!v) return ''; 
                        return v.toString().replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); 
                    } 
                }">
                    <flux:input label="Harga Beli Dasar (HPP)" placeholder="0" required x-bind:value="format(val)" x-on:input="val = $event.target.value.replace(/\D/g, '')">
                        <x-slot name="icon">
                            <span class="text-zinc-500 font-medium pl-2">Rp</span>
                        </x-slot>
                    </flux:input>
                </div>
                
                {{-- Harga Jual dengan AlpineJS Masking --}}
                <div x-data="{ 
                    val: @entangle('selling_price').live,
                    format(v) { 
                        if (!v) return ''; 
                        return v.toString().replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); 
                    } 
                }">
                    <flux:input label="Harga Jual Dasar" placeholder="0" required x-bind:value="format(val)" x-on:input="val = $event.target.value.replace(/\D/g, '')">
                        <x-slot name="icon">
                            <span class="text-zinc-500 font-medium pl-2">Rp</span>
                        </x-slot>
                    </flux:input>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end bg-zinc-50 dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
                <flux:input type="number" wire:model="min_stock" label="Stok Min" placeholder="0" min="0" required />
                <flux:input type="number" wire:model="max_stock" label="Stok Max" placeholder="0" min="0" required />
                
                <div class="pb-2 pl-2">
                    <flux:checkbox wire:model="is_active" label="Status Aktif" />
                </div>
                <div class="pb-2">
                    <flux:checkbox wire:model="requires_label" label="Wajib Scan SN" />
                </div>
            </div>
            
            <div class="flex justify-end gap-2 mt-6">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $item_id ? 'Simpan Perubahan' : 'Tambahkan Barang' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
