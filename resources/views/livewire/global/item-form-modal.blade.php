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
    
    #[Rule('nullable|string|max:100')]
    public $alias = '';
    
    #[Rule('required|string|max:100')]
    public $name = '';
    
    #[Rule('nullable|array')]
    public $tags = [];
    
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
    public $availableTags = [];

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
        $this->availableTags = Item::whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->values()
            ->toArray();
        
        if ($id) {
            $item = Item::findOrFail($id);
            $this->item_id = $item->id;
            $this->code = $item->code;
            $this->alias = $item->alias;
            $this->name = $item->name;
            $this->tags = $item->tags ?? [];
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
            $this->alias = '';
            $this->name = '';
            $this->tags = [];
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
        
        if (!empty($validated['alias'])) {
            $validated['alias'] = strtoupper(trim($validated['alias']));
        }

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
            // Automatically require approval if created by non-warehouse users or outside inventory module
            $isWarehouseUser = auth()->user()->hasRole('Gudang') || auth()->user()->hasRole('Super Admin');
            if (!$this->isInventoryUrl || !$isWarehouseUser) {
                $validated['is_approved'] = false;
                $validated['is_active'] = false;
            } else {
                $validated['is_approved'] = true;
            }

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

    <flux:modal name="item-modal" class="md:max-w-4xl">

        {{-- Modal Header --}}
        <div class="flex items-center gap-4 mb-6">
            <div class="p-2.5 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl text-indigo-600 dark:text-indigo-400 shadow-sm shrink-0">
                @if ($item_id)
                    <flux:icon.pencil-square class="w-5 h-5" />
                @else
                    <flux:icon.cube class="w-5 h-5" />
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <flux:heading size="lg" class="!mb-0">{{ $item_id ? 'Edit Barang' : 'Tambah Barang Baru' }}</flux:heading>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                    {{ $item_id ? 'Perbarui informasi dan detail barang' : 'Isi informasi lengkap untuk mendaftarkan barang baru' }}
                </p>
            </div>
            @if ($code)
                <div class="text-xs font-mono bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 px-2.5 py-1 rounded-lg shrink-0">{{ $code }}</div>
            @endif
        </div>

        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-5">

                {{-- KOLOM KIRI: Foto & Deskripsi (2/5) --}}
                <div class="md:col-span-2 border-b md:border-b-0 md:border-r border-zinc-200 dark:border-zinc-700 p-6 flex flex-col gap-5 bg-zinc-50/30 dark:bg-zinc-800/10">

                    {{-- Area Foto --}}
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-full max-w-[192px]">
                            <x-image-cropper id="item-cropper" wire:model="image" :image="$image" accept="image/*" />
                        </div>
                        <span class="text-[11px] text-zinc-400 dark:text-zinc-500 font-medium text-center">Klik untuk ganti foto utama</span>
                    </div>

                    <div class="border-t border-dashed border-zinc-200 dark:border-zinc-700"></div>

                    {{-- Nama Barang & Alias --}}
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <flux:input wire:model="alias" label="Alias (Seri / Merek)" placeholder="Contoh: BILLY, RAHWANA" maxlength="100" />
                            <p class="text-[11px] text-zinc-400">Kosongkan jika tidak ada nama seri khusus.</p>
                        </div>
                        <div class="space-y-1">
                            <flux:input wire:model="name" label="Nama Barang" placeholder="Contoh: Rak Buku Putih, Dipan Jati 160x200" required maxlength="100" />
                            <p class="text-[11px] text-zinc-400">Maks. 100 karakter.</p>
                        </div>
                    </div>

                    {{-- Tags --}}
                    <div class="space-y-1" x-data="{
                        newTag: '',
                        tags: @entangle('tags'),
                        availableTags: @entangle('availableTags'),
                        showSuggestions: false,
                        addTag(tag = null) {
                            tag = (tag || this.newTag).trim();
                            if (tag && !this.tags.includes(tag)) {
                                this.tags.push(tag);
                            }
                            this.newTag = '';
                            this.showSuggestions = false;
                        },
                        removeTag(index) {
                            this.tags.splice(index, 1);
                        },
                        get filteredSuggestions() {
                            if (!this.newTag) return [];
                            return this.availableTags.filter(t => 
                                t.toLowerCase().includes(this.newTag.toLowerCase()) && 
                                !this.tags.includes(t)
                            );
                        }
                    }">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Label / Tags</label>
                        <div class="relative">
                            <div class="min-h-[42px] p-1.5 flex flex-wrap gap-1.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-sm focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:border-indigo-500 transition-shadow">
                                <template x-for="(tag, index) in tags" :key="index">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 rounded-md border border-indigo-100 dark:border-indigo-500/20">
                                        <span x-text="tag"></span>
                                        <button type="button" @click.stop="removeTag(index)" class="text-indigo-400 hover:text-indigo-600 focus:outline-none">
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                                        </button>
                                    </span>
                                </template>
                                <input type="text" 
                                       x-model="newTag" 
                                       @keydown.enter.prevent="addTag()" 
                                       @keydown.backspace="newTag === '' && tags.length > 0 ? removeTag(tags.length - 1) : null"
                                       @input="showSuggestions = true"
                                       @click="showSuggestions = true"
                                       @click.away="showSuggestions = false"
                                       placeholder="Ketik tag & tekan Enter..." 
                                       class="flex-1 min-w-[140px] bg-transparent border-0 p-1 text-sm focus:ring-0 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400">
                            </div>
                            
                            {{-- Suggestions Dropdown --}}
                            <div x-show="showSuggestions && filteredSuggestions.length > 0" 
                                 x-transition.opacity
                                 class="absolute z-10 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg overflow-hidden max-h-48 overflow-y-auto"
                                 style="display: none;">
                                <template x-for="suggestion in filteredSuggestions" :key="suggestion">
                                    <div @click="addTag(suggestion)" 
                                         class="px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer flex items-center gap-2">
                                        <flux:icon.plus class="w-4 h-4 text-zinc-400" />
                                        <span x-text="suggestion"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Deskripsi --}}
                    <div class="flex-1">
                        <flux:textarea wire:model="description" label="Spesifikasi / Deskripsi" placeholder="Tuliskan spesifikasi lengkap, ukuran, atau keterangan tambahan..." rows="4" />
                    </div>

                </div>

                {{-- KOLOM KANAN: Detail Teknis, Harga, Stok (3/5) --}}
                <div class="md:col-span-3 p-6 flex flex-col gap-6">

                    {{-- Seksi: Klasifikasi --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 bg-violet-50 dark:bg-violet-500/10 rounded-lg text-violet-500">
                                <flux:icon.tag class="w-4 h-4" />
                            </div>
                            <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">Klasifikasi</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {{-- Satuan --}}
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Satuan <span class="text-red-500">*</span></label>
                                <div class="flex gap-1.5">
                                    <flux:select wire:model.live="unit_id" class="flex-1 min-w-0">
                                        <flux:select.option value="">-- Pilih Satuan --</flux:select.option>
                                        @foreach($units as $unit)
                                            <flux:select.option value="{{ $unit->id }}">{{ $unit->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <button type="button" wire:click="$dispatch('open-unit-modal')" wire:loading.attr="disabled" class="shrink-0 flex items-center justify-center w-8 h-8 mt-0.5 rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-500 hover:text-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Tambah Baru">
                                        <svg wire:loading.remove wire:target="$dispatch('open-unit-modal')" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>
                                        <flux:icon.spinner wire:loading wire:target="$dispatch('open-unit-modal')" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            {{-- Tipe Barang --}}
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Tipe Barang <span class="text-red-500">*</span></label>
                                <div class="flex gap-1.5">
                                    <flux:select wire:model.live="type_id" class="flex-1 min-w-0">
                                        <flux:select.option value="">-- Pilih Tipe --</flux:select.option>
                                        @foreach($types as $type)
                                            <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <button type="button" wire:click="$dispatch('open-type-modal')" wire:loading.attr="disabled" class="shrink-0 flex items-center justify-center w-8 h-8 mt-0.5 rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-500 hover:text-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Tambah Baru">
                                        <svg wire:loading.remove wire:target="$dispatch('open-type-modal')" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>
                                        <flux:icon.spinner wire:loading wire:target="$dispatch('open-type-modal')" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            {{-- Kategori Utama --}}
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Kategori <span class="text-red-500">*</span></label>
                                <div class="flex gap-1.5">
                                    <flux:select wire:model.live="category_id" class="flex-1 min-w-0">
                                        <flux:select.option value="">-- Pilih Kategori --</flux:select.option>
                                        @foreach($categories as $category)
                                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <button type="button" wire:click="$dispatch('open-category-modal')" wire:loading.attr="disabled" class="shrink-0 flex items-center justify-center w-8 h-8 mt-0.5 rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-500 hover:text-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Tambah Baru">
                                        <svg wire:loading.remove wire:target="$dispatch('open-category-modal')" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>
                                        <flux:icon.spinner wire:loading wire:target="$dispatch('open-category-modal')" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            {{-- Sub Kategori --}}
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Sub Kategori</label>
                                <div class="flex gap-1.5">
                                    <flux:select wire:model.live="sub_category_id" class="flex-1 min-w-0" :disabled="!$category_id">
                                        <flux:select.option value="">-- Pilih Sub --</flux:select.option>
                                        @foreach($subcategories as $sub)
                                            <flux:select.option value="{{ $sub->id }}">{{ $sub->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <button type="button" wire:click="$dispatch('open-sub-category-modal')" wire:loading.attr="disabled" class="shrink-0 flex items-center justify-center w-8 h-8 mt-0.5 rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-500 hover:text-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Tambah Baru">
                                        <svg wire:loading.remove wire:target="$dispatch('open-sub-category-modal')" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>
                                        <flux:icon.spinner wire:loading wire:target="$dispatch('open-sub-category-modal')" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-zinc-200 dark:border-zinc-700"></div>

                    {{-- Seksi: Harga --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 bg-emerald-50 dark:bg-emerald-500/10 rounded-lg text-emerald-500">
                                <flux:icon.banknotes class="w-4 h-4" />
                            </div>
                            <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">Harga Dasar</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Harga Beli <span class="text-red-500">*</span></label>
                                <x-currency-input wire:model="purchase_price" placeholder="0" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Harga Jual <span class="text-red-500">*</span></label>
                                <x-currency-input wire:model="selling_price" placeholder="0" required />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-zinc-200 dark:border-zinc-700"></div>

                    {{-- Seksi: Stok --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 bg-blue-50 dark:bg-blue-500/10 rounded-lg text-blue-500">
                                <flux:icon.archive-box class="w-4 h-4" />
                            </div>
                            <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">Batas Stok</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Stok Minimum <span class="text-red-500">*</span></label>
                                <flux:input type="number" wire:model.live="min_stock" placeholder="0" required min="0" />
                                <p class="text-[11px] text-zinc-400 mt-1">Notifikasi stok kritis</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Stok Maksimum <span class="text-red-500">*</span></label>
                                <flux:input type="number" wire:model.live="max_stock" placeholder="0" required x-bind:min="$wire.min_stock || 0" />
                                <p class="text-[11px] text-zinc-400 mt-1">Batas stok ideal</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- FOOTER --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pt-6 mt-6 border-t border-zinc-200 dark:border-zinc-700 gap-4">
                <div class="flex items-center gap-6">
                    @if ($this->isInventoryUrl)
                        <flux:switch wire:model="is_active" label="Status Aktif" />
                    @endif
                    <flux:switch wire:model="requires_label" label="Cetak Label" />
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" icon="{{ $item_id ? 'check' : 'plus' }}">
                        {{ $item_id ? 'Simpan Perubahan' : 'Simpan Barang' }}
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    {{-- Komponen pendukung untuk Quick Add (agar otomatis terpanggil jika item-form-modal digunakan) --}}
    <livewire:item-input.unit-form />
    <livewire:item-input.type-form />
    <livewire:item-input.category-form />
    <livewire:item-input.sub-category-form />
</div>
