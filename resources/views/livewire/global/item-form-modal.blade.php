<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Type;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\SubCategory;
use Illuminate\Support\Str;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;

    public $item_id = null;
    public $code = '';
    
    #[Rule('required|string|max:255')]
    public $name = '';
    
    #[Rule('nullable|string')]
    public $description = '';
    
    #[Rule('required')]
    public $image = null;
    
    #[Rule('required|exists:units,id')]
    public $unit_id = '';
    
    #[Rule('required|exists:types,id')]
    public $type_id = '';
    
    #[Rule('required|exists:categories,id')]
    public $category_id = '';
    
    #[Rule('nullable|exists:sub_categories,id')]
    public $sub_category_id = '';
    
    #[Rule('required|numeric|min:0')]
    public $purchase_price = null;
    
    #[Rule('required|numeric|min:0|gte:purchase_price')]
    public $selling_price = null;
    
    #[Rule('required|integer|min:0')]
    public $min_stock = null;
    
    #[Rule('required|integer|min:0|gte:min_stock')]
    public $max_stock = null;
    
    #[Rule('boolean')]
    public $is_active = true;
    
    #[Rule('boolean')]
    public $requires_label = false;

    public $isInventoryUrl = true;

    public $units = [];
    public $types = [];
    public $categories = [];
    public $subcategories = [];

    public function mount()
    {
        $this->units = Unit::orderBy('name')->get();
        $this->types = Type::orderBy('name')->get();
        $this->categories = Category::orderBy('name')->get();
    }

    public function updatedCategoryId($value)
    {
        if ($value) {
            $this->subcategories = SubCategory::where('category_id', $value)->orderBy('name')->get();
        } else {
            $this->subcategories = [];
        }
        $this->sub_category_id = '';
        $this->dispatch('subcategory-updated', options: $this->subcategories);
    }

    #[On('open-item-modal')]
    public function openModal($id = null)
    {
        $this->resetValidation();
        $this->units = Unit::orderBy('name')->get();
        $this->types = Type::orderBy('name')->get();
        $this->categories = Category::orderBy('name')->get();
        
        if ($id) {
            $item = Item::findOrFail($id);
            $this->item_id = $item->id;
            $this->code = $item->code;
            $this->name = $item->name;
            $this->description = $item->description;
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
            $this->isInventoryUrl = true; // Saat edit, biarkan switch muncul
            $this->requires_label = $item->requires_label;
        } else {
            $this->item_id = null;
            $this->code = '';
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
            // Otomatis non-aktif jika ditambahkan dari luar modul Inventory (misal: dari Purchasing)
            $this->isInventoryUrl = request()->routeIs('inventory.items');
            $this->is_active = $this->isInventoryUrl;
            
            $this->requires_label = false;
        }
        
        $this->dispatch('unit-updated', options: $this->units);
        $this->dispatch('type-updated', options: $this->types);
        $this->dispatch('category-updated', options: $this->categories);
        $this->dispatch('subcategory-updated', options: $this->subcategories);

        $this->dispatch('item-modal-loaded');
        Flux::modal('item-modal')->show();
    }

    #[On('unit-updated')]
    public function handleUnitUpdated($id = null) { 
        $this->units = Unit::orderBy('name')->get(); 
        if ($id) $this->unit_id = $id;
    }

    #[On('type-updated')]
    public function handleTypeUpdated($id = null) { 
        $this->types = Type::orderBy('name')->get(); 
        if ($id) $this->type_id = $id;
    }

    #[On('category-updated')]
    public function handleCategoryUpdated($id = null) { 
        $this->categories = Category::orderBy('name')->get(); 
        if ($id) {
            $this->category_id = $id;
            $this->subcategories = SubCategory::where('category_id', $id)->orderBy('name')->get();
            $this->sub_category_id = '';
            $this->dispatch('subcategory-updated', options: $this->subcategories);
        }
    }

    #[On('subcategory-updated')]
    public function handleSubcategoryUpdated($id = null) { 
        if ($this->category_id) {
            $this->subcategories = SubCategory::where('category_id', $this->category_id)->orderBy('name')->get();
            if ($id) $this->sub_category_id = $id;
        }
    }

    public function save() {
        if (! $this->item_id) {
            $lastItem = Item::orderBy('id', 'desc')->first();
            $nextId = $lastItem ? $lastItem->id + 1 : 1;
            $this->code = 'ITM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
        }

        $validated = $this->validate();
        $validated['code'] = $this->code;
        $validated['user_id'] = auth()->id();
        
        $validated['unit_id'] = $validated['unit_id'] ?: null;
        $validated['type_id'] = $validated['type_id'] ?: null;
        $validated['category_id'] = $validated['category_id'] ?: null;
        $validated['sub_category_id'] = $validated['sub_category_id'] ?: null;

        if (is_string($this->image) && str_starts_with($this->image, 'data:image/webp;base64,')) {
            $base64Image = substr($this->image, strpos($this->image, ',') + 1);
            $imageData = base64_decode($base64Image);
            
            $filename = 'items/' . uniqid() . '.webp';
            \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $imageData);
            
            $validated['image'] = $filename;
        } elseif ($this->image === null) {
            $validated['image'] = null;
        } else {
            unset($validated['image']);
        }

        if ($this->item_id) {
            $item = Item::find($this->item_id);
            $oldIsActive = $item->is_active;
            $oldName = $item->name;
            
            if ($item->image && (array_key_exists('image', $validated) || $this->image === null)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($item->image);
            }
            
            $item->update($validated);
            $actionType = 'diperbarui';

            $recipients = \App\Models\User::permission('inventory.notifikasi.view')
                ->orWhereHas('roles', function($q) { $q->where('name', 'Super Admin'); })
                ->get();

            if ($oldIsActive !== $item->is_active) {
                $notification = new \App\Notifications\ItemStatusChangedNotification($item, auth()->user());
                \Illuminate\Support\Facades\Notification::send($recipients, $notification);
            } else {
                $notification = new \App\Notifications\ItemUpdatedNotification($item, auth()->user(), $oldName);
                \Illuminate\Support\Facades\Notification::send($recipients, $notification);
            }
        } else {
            $item = Item::create($validated);
            $actionType = 'ditambahkan';

            $recipients = \App\Models\User::permission('inventory.notifikasi.view')
                ->orWhereHas('roles', function($q) { $q->where('name', 'Super Admin'); })
                ->get();
            $notification = new \App\Notifications\ItemAddedNotification($item, auth()->user());
            \Illuminate\Support\Facades\Notification::send($recipients, $notification);
        }

        Flux::modal('item-modal')->close();
        $this->dispatch('item-saved');
        
        \App\Events\InventoryUpdated::safeDispatch("Data barang {$validated['code']} berhasil {$actionType}");
    }
};
?>

<div>
    <div x-on:trigger-add-subcategory.window="$wire.dispatch('open-subcategory-modal', { category_id: $wire.category_id })"></div>

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
                            <x-image-cropper id="item-cropper" wire:model="image" :image="$image" accept="image/*" />
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
                    @if ($this->isInventoryUrl)
                    <flux:switch wire:model="is_active" label="Status Aktif" />
                    @endif
                    <div class="mr-10"></div>
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
