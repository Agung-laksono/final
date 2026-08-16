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
    
    public $suggestedAliases = [];

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
    public $purchase_price = 0;
    
    #[Rule('required|numeric|min:0|gte:purchase_price')]
    public $selling_price = 0;
    
    #[Rule('required|integer|min:0')]
    public $min_stock = 0;
    
    #[Rule('required|integer|min:0|gte:min_stock')]
    public $max_stock = 0;
    
    #[Rule('boolean')]
    public $is_active = true;
    
    #[Rule('boolean')]
    public $requires_label = true;

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

    public function updatedName($value)
    {
        if (strlen(trim($value)) >= 3) {
            $this->suggestLocalAliases(true);
        } else {
            $this->suggestedAliases = [];
        }
    }

    #[On('open-item-modal')]
    public function openModal($id = null)
    {
        // Livewire.dispatch() dari JS mengirim params sebagai array — extract 'id' jika perlu
        if (is_array($id) && isset($id['id'])) {
            $id = $id['id'];
        }
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
            $this->suggestedAliases = [];
            $this->name = '';
            $this->tags = [];
            $this->description = '';
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
            // Otomatis aktif jika diinput dari modul Inventory ATAU jika user memiliki Role Gudang / Admin
            $isInventoryUser = auth()->check() && auth()->user()->hasAnyRole([
                'Super Admin', 'Manager', 'Kepala Gudang', 'Staf Gudang', 'Staf Gudang PPIC', 'Staf Gudang Fulfillment'
            ]);
            
            $this->isInventoryUrl = request()->routeIs('inventory.items') || $isInventoryUser;
            $this->is_active = $this->isInventoryUrl;
            
            $this->requires_label = true;
        }
        
        $this->dispatch('unit-updated', options: $this->units);
        $this->dispatch('type-updated', options: $this->types);
        $this->dispatch('category-updated', options: $this->categories);
        $this->dispatch('subcategory-updated', options: $this->subcategories);

        $this->dispatch('item-modal-loaded');
        // Dispatch event khusus — diproses setelah 3x queueMicrotask di Livewire JS,
        // memberi Alpine cukup waktu selesai inisialisasi sebelum modal dibuka
        $this->dispatch('do-open-item-modal');
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

        // Terima WebP (Android/Chrome) maupun JPEG (fallback iOS Safari)
        if (is_string($this->image) && preg_match('/^data:image\/(webp|jpeg|jpg|png);base64,/', $this->image, $matches)) {
            $mime = $matches[1];
            $ext  = in_array($mime, ['jpeg', 'jpg']) ? 'jpg' : $mime;

            $base64Image = substr($this->image, strpos($this->image, ',') + 1);
            $imageData   = base64_decode($base64Image);

            $filename = 'items/' . uniqid() . '.' . $ext;
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

    public function suggestLocalAliases($silent = false)
    {
        if (empty(trim($this->name))) {
            if (!$silent) Flux::toast('Silakan isi Nama Barang terlebih dahulu', variant: 'warning');
            return;
        }

        $words = explode(' ', trim($this->name));
        $keyword1 = $words[0] ?? '';
        $keyword2 = $words[1] ?? '';

        $query = Item::whereNotNull('alias')->where('alias', '!=', '');

        $query->where(function($q) use ($keyword1, $keyword2) {
            if ($this->category_id) {
                $q->orWhere('category_id', $this->category_id);
            }
            if ($keyword1) {
                $q->orWhere('name', 'like', "%{$keyword1}%");
            }
            if ($keyword2) {
                $q->orWhere('name', 'like', "%{$keyword2}%");
            }
        });

        if ($this->item_id) {
            $query->where('id', '!=', $this->item_id);
        }

        $this->suggestedAliases = $query->inRandomOrder()
            ->take(10)
            ->pluck('alias')
            ->unique()
            ->take(5)
            ->values()
            ->toArray();
            
        if (empty($this->suggestedAliases)) {
            $this->suggestedAliases = Item::whereNotNull('alias')
                ->where('alias', '!=', '')
                ->inRandomOrder()
                ->take(5)
                ->pluck('alias')
                ->unique()
                ->values()
                ->toArray();
                
            if (empty($this->suggestedAliases)) {
                if (!$silent) Flux::toast('Belum ada riwayat alias yang bisa direkomendasikan.', variant: 'warning');
                return;
            }
        }
    }
};
?>

<div x-on:do-open-item-modal.window="Flux.modal('item-modal').show()">

    <flux:modal name="item-modal" class="md:max-w-4xl">
        <div x-data="{ step: 1 }" x-on:item-modal-loaded.window="step = 1">
            {{-- Modal Header --}}
            <div class="flex items-center gap-4 mb-4">
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
                        <span x-show="step === 1">Langkah 1: Identitas & Wujud Barang</span>
                        <span x-show="step === 2" style="display: none;">Langkah 2: Klasifikasi & Pengelompokan</span>
                        <span x-show="step === 3" style="display: none;">Langkah 3: Spesifikasi & Label Tambahan</span>
                        <span x-show="step === 4" style="display: none;">Langkah 4: Operasional & Angka Dasar</span>
                        <span x-show="step === 5" style="display: none;">Langkah 5: Tinjauan Akhir (Review)</span>
                    </p>
                </div>
                @if ($code)
                    <div class="text-xs font-mono bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 px-2.5 py-1 rounded-lg shrink-0">{{ $code }}</div>
                @endif
            </div>

            {{-- Progress Indicator --}}
            <div class="flex gap-1.5 mb-8" x-show="step < 5">
                <div class="h-1.5 flex-1 rounded-full transition-colors duration-300" :class="step >= 1 ? 'bg-indigo-600 dark:bg-indigo-500' : 'bg-zinc-200 dark:bg-zinc-700'"></div>
                <div class="h-1.5 flex-1 rounded-full transition-colors duration-300" :class="step >= 2 ? 'bg-indigo-600 dark:bg-indigo-500' : 'bg-zinc-200 dark:bg-zinc-700'"></div>
                <div class="h-1.5 flex-1 rounded-full transition-colors duration-300" :class="step >= 3 ? 'bg-indigo-600 dark:bg-indigo-500' : 'bg-zinc-200 dark:bg-zinc-700'"></div>
                <div class="h-1.5 flex-1 rounded-full transition-colors duration-300" :class="step >= 4 ? 'bg-indigo-600 dark:bg-indigo-500' : 'bg-zinc-200 dark:bg-zinc-700'"></div>
            </div>
            {{-- Progress Indicator Step 5 (Full) --}}
            <div class="flex gap-1.5 mb-8" x-show="step === 5" style="display: none;">
                <div class="h-1.5 w-full rounded-full transition-colors duration-300 bg-emerald-500 dark:bg-emerald-400"></div>
            </div>

            <form wire:submit="save">
                
                <div :class="step === 5 ? 'grid grid-cols-1 md:grid-cols-5 gap-6 w-full' : 'w-full sm:w-[42rem] max-w-full mx-auto'">
                    
                    {{-- KOLOM KIRI (Langkah 1 & 3 pada Step 5) --}}
                    <div :class="step === 5 ? 'md:col-span-2 flex flex-col gap-6' : ''">

                        {{-- STEP 1: IDENTITAS --}}
                        <div x-show="step === 1 || step === 5" x-transition.opacity :class="step === 5 ? 'p-5 bg-zinc-50/30 dark:bg-zinc-800/10 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-6' : 'space-y-6'" style="display: none;">
                            <div class="flex flex-col items-center gap-2 mb-4">
                                <div class="w-full max-w-[192px]">
                                    <x-image-cropper id="item-cropper" wire:model="image" :image="$image" accept="image/*" />
                                </div>
                                <span class="text-[11px] text-zinc-400 dark:text-zinc-500 font-medium text-center">Klik untuk ganti foto utama</span>
                            </div>

                            <flux:input wire:model.live.debounce.1000ms="name" label="Nama Barang" placeholder="Contoh: Rak Buku Putih, Dipan Jati 160x200" required maxlength="100" />
                            
                            <div class="relative">
                                <flux:input wire:model="alias" label="Alias (Seri / Merek)" placeholder="Opsional, cth: BILLY, RAHWANA" maxlength="100" />
                                
                                @if(count($suggestedAliases) > 0)
                                <div class="mt-2.5 flex flex-wrap items-center gap-2" x-transition.opacity>
                                    <span class="text-[10px] text-zinc-500 font-bold uppercase tracking-wider">Ide dari riwayat:</span>
                                    @foreach($suggestedAliases as $suggestion)
                                        <button type="button" 
                                            wire:click="$set('alias', '{{ $suggestion }}')" 
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 hover:scale-105 active:scale-95 dark:bg-indigo-500/10 dark:text-indigo-400 dark:hover:bg-indigo-500/20 border border-indigo-100 dark:border-indigo-500/20 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-zinc-900">
                                            <flux:icon.sparkles class="w-3 h-3 opacity-60" />
                                            {{ $suggestion }}
                                        </button>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- STEP 3: SPESIFIKASI --}}
                        <div x-show="step === 3 || step === 5" x-transition.opacity :class="step === 5 ? 'p-5 bg-zinc-50/30 dark:bg-zinc-800/10 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-6' : 'space-y-6'" style="display: none;">
                            {{-- Deskripsi --}}
                            <flux:textarea wire:model="description" label="Spesifikasi / Deskripsi" placeholder="Tuliskan spesifikasi lengkap, ukuran, atau keterangan tambahan..." rows="4" />

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
                                    let available = this.availableTags || [];
                                    if (!this.newTag) {
                                        return available.filter(t => !this.tags.includes(t)).slice(0, 15);
                                    }
                                    return available.filter(t => 
                                        t.toLowerCase().includes(this.newTag.toLowerCase()) && 
                                        !this.tags.includes(t)
                                    ).slice(0, 15);
                                }
                            }">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Label / Tags (Opsional)</label>
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
                                        <div class="flex-1 flex items-center min-w-[140px]">
                                            <input type="text" 
                                                x-model="newTag" 
                                                @keydown.enter.prevent="addTag()" 
                                                @keydown.comma.prevent="addTag()"
                                                @keydown.backspace="newTag === '' && tags.length > 0 ? removeTag(tags.length - 1) : null"
                                                @input="showSuggestions = true"
                                                @click="showSuggestions = true"
                                                @click.away="showSuggestions = false"
                                                placeholder="Ketik tag & tekan Enter / Koma" 
                                                class="w-full bg-transparent border-0 p-1 text-sm focus:ring-0 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400">
                                            <button type="button" x-show="newTag.trim() !== ''" @click.stop="addTag()" class="p-1 text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 transition-colors" title="Tambah Tag">
                                                <flux:icon.plus class="w-4 h-4" />
                                            </button>
                                        </div>
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
                        </div>

                    </div> <!-- End of Kolom Kiri -->

                    {{-- KOLOM KANAN (Langkah 2 & 4 pada Step 5) --}}
                    <div :class="step === 5 ? 'md:col-span-3 flex flex-col gap-6' : ''">

                        {{-- STEP 2: KLASIFIKASI --}}
                        <div x-show="step === 2 || step === 5" x-transition.opacity :class="step === 5 ? 'p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-6 shadow-sm' : 'space-y-6'" style="display: none;">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                {{-- Kategori Utama --}}
                                <div>
                                    <div class="flex justify-between items-center mb-1.5">
                                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Kategori <span class="text-red-500">*</span></label>
                                        <button type="button" x-on:click="$flux.modal('category-modal').show()" class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors flex items-center gap-0.5" title="Tambah Baru">
                                            <flux:icon.plus class="w-3 h-3" /> Baru
                                        </button>
                                    </div>
                                    <flux:select wire:model.live="category_id" class="w-full">
                                        <flux:select.option value="">-- Pilih Kategori --</flux:select.option>
                                        @foreach($categories as $category)
                                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                {{-- Sub Kategori --}}
                                <div>
                                    <div class="flex justify-between items-center mb-1.5">
                                        <label class="text-sm font-medium" :class="!$wire.category_id ? 'text-zinc-400 dark:text-zinc-500' : 'text-zinc-700 dark:text-zinc-300'">Sub Kategori</label>
                                        <button type="button" x-bind:disabled="!$wire.category_id" x-on:click="$dispatch('open-subcategory-modal', { category_id: $wire.category_id })" class="text-[11px] font-medium transition-colors flex items-center gap-0.5" :class="!$wire.category_id ? 'text-zinc-400 dark:text-zinc-500 cursor-not-allowed' : 'text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300'" title="Tambah Baru">
                                            <flux:icon.plus class="w-3 h-3" /> Baru
                                        </button>
                                    </div>
                                    <flux:select wire:model.live="sub_category_id" class="w-full" x-bind:disabled="!$wire.category_id">
                                        <flux:select.option value="">-- Pilih Sub --</flux:select.option>
                                        @foreach($subcategories as $sub)
                                            <flux:select.option value="{{ $sub->id }}">{{ $sub->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                {{-- Tipe Barang --}}
                                <div>
                                    <div class="flex justify-between items-center mb-1.5">
                                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Tipe Barang <span class="text-red-500">*</span></label>
                                        <button type="button" x-on:click="$flux.modal('type-modal').show()" class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors flex items-center gap-0.5" title="Tambah Baru">
                                            <flux:icon.plus class="w-3 h-3" /> Baru
                                        </button>
                                    </div>
                                    <flux:select wire:model.live="type_id" class="w-full">
                                        <flux:select.option value="">-- Pilih Tipe --</flux:select.option>
                                        @foreach($types as $type)
                                            <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                {{-- Satuan --}}
                                <div>
                                    <div class="flex justify-between items-center mb-1.5">
                                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Satuan <span class="text-red-500">*</span></label>
                                        <button type="button" x-on:click="$flux.modal('unit-modal').show()" class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors flex items-center gap-0.5" title="Tambah Baru">
                                            <flux:icon.plus class="w-3 h-3" /> Baru
                                        </button>
                                    </div>
                                    <flux:select wire:model.live="unit_id" class="w-full">
                                        <flux:select.option value="">-- Pilih Satuan --</flux:select.option>
                                        @foreach($units as $unit)
                                            <flux:select.option value="{{ $unit->id }}">{{ $unit->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            </div>
                        </div>

                        {{-- STEP 4: HARGA & STOK --}}
                        <div x-show="step === 4 || step === 5" x-transition.opacity :class="step === 5 ? 'p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-6 shadow-sm' : 'space-y-6'" style="display: none;">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Harga Beli / HPP <span class="text-red-500">*</span></label>
                                    <x-currency-input wire:model="purchase_price" placeholder="0" required />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Harga Jual <span class="text-red-500">*</span></label>
                                    <x-currency-input wire:model="selling_price" placeholder="0" required />
                                </div>
                            </div>

                            <div class="border-t border-dashed border-zinc-200 dark:border-zinc-700"></div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Stok Min (Kritis) <span class="text-red-500">*</span></label>
                                    <flux:input type="number" inputmode="numeric" pattern="[0-9]*" wire:model.live="min_stock" placeholder="0" required min="0" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Stok Max (Ideal) <span class="text-red-500">*</span></label>
                                    <flux:input type="number" inputmode="numeric" pattern="[0-9]*" wire:model.live="max_stock" placeholder="0" required x-bind:min="$wire.min_stock || 0" />
                                </div>
                            </div>

                            <div class="border-t border-dashed border-zinc-200 dark:border-zinc-700"></div>

                            <div class="flex items-center gap-6 pt-2">
                                @if ($this->isInventoryUrl)
                                    <flux:switch wire:model="is_active" label="Status Aktif" />
                                @endif
                                <flux:switch wire:model="requires_label" label="Cetak Label QR" />
                            </div>
                        </div>

                    </div> <!-- End of Kolom Kanan -->
                </div> <!-- End of Main Wrapper Grid -->

                {{-- FOOTER NAVIGATION --}}
                <div class="flex items-center justify-between pt-6 mt-8 border-t border-zinc-200 dark:border-zinc-700">
                    <div class="flex gap-2">
                        <flux:modal.close x-show="step === 1">
                            <flux:button variant="ghost">Batal</flux:button>
                        </flux:modal.close>
                        <flux:button x-show="step > 1" variant="ghost" x-on:click="step--" style="display: none;" icon="chevron-left">Sebelumnya</flux:button>
                    </div>

                    <div class="flex gap-2">
                        <flux:button x-show="step < 5" variant="primary" x-on:click="step++" icon-trailing="chevron-right">
                            <span x-show="step < 4">Selanjutnya</span>
                            <span x-show="step === 4" style="display: none;">Tinjau Data</span>
                        </flux:button>
                        <flux:button x-show="step === 5" type="submit" variant="primary" icon="{{ $item_id ? 'check' : 'plus' }}" style="display: none;">
                            {{ $item_id ? 'Simpan Perubahan' : 'Simpan Barang' }}
                        </flux:button>
                    </div>
                </div>

            </form>
        </div>
    </flux:modal>

    {{-- Komponen pendukung untuk Quick Add (agar otomatis terpanggil jika item-form-modal digunakan) --}}
    <livewire:item-input.unit-form />
    <livewire:item-input.type-form />
    <livewire:item-input.category-form />
    <livewire:item-input.sub-category-form />
</div>
