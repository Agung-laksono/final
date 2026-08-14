<?php

use Livewire\Volt\Component;
use App\Models\NavigationItem;
use App\Services\NavigationService;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public array $editingItem = [];
    public bool $showIconPicker = false;
    public string $iconSearch = '';
    public ?int $editingId = null;
    
    public $imageUpload;
    public string $activeTab = 'flux'; // 'flux' atau 'image'

    // Daftar icon Flux/Heroicons yang relevan untuk bisnis
    public array $availableIcons = [
        // Navigation & UI
        'home', 'link', 'bars-3', 'squares-2x2', 'view-columns',
        // Business
        'chart-pie', 'chart-bar', 'presentation-chart-line', 'banknotes', 'credit-card',
        'receipt-percent', 'calculator', 'currency-dollar',
        // Inventory & Stock
        'cube', 'cube-transparent', 'building-storefront', 'building-office-2',
        'arrows-right-left', 'arrow-right-end-on-rectangle', 'arrow-left-end-on-rectangle',
        'arrow-down-tray', 'arrow-up-tray', 'truck', 'adjustments-horizontal',
        // Documents
        'document', 'document-text', 'document-duplicate', 'document-check',
        'clipboard-document-list', 'clipboard-document-check', 'clipboard',
        'newspaper', 'inbox-arrow-down', 'inbox', 'queue-list',
        // People & Org
        'user', 'user-group', 'users', 'identification',
        // Products & Tools
        'wrench', 'wrench-screwdriver', 'cog', 'cog-6-tooth', 'cog-8-tooth',
        'beaker', 'fire', 'tag', 'shopping-cart', 'shopping-bag',
        // Communication
        'chat-bubble-left-right', 'chat-bubble-left', 'bell', 'envelope',
        'phone', 'megaphone',
        // Time & Status
        'clock', 'calendar', 'calendar-days', 'check-circle', 'x-circle',
        'exclamation-circle', 'information-circle',
        // Location
        'map', 'map-pin', 'globe-alt',
        // Data
        'table-cells', 'funnel', 'magnifying-glass', 'server',
    ];

    #[Computed]
    public function groupedItems()
    {
        return NavigationItem::orderBy('section')->orderBy('sort_order')
            ->get()
            ->groupBy('section');
    }

    #[Computed]
    public function filteredIcons()
    {
        if (empty($this->iconSearch)) {
            return $this->availableIcons;
        }
        return array_values(array_filter(
            $this->availableIcons,
            fn($icon) => str_contains($icon, strtolower($this->iconSearch))
        ));
    }

    public function openIconPicker(int $id): void
    {
        $item = NavigationItem::find($id);
        if (!$item) return;

        $this->editingId   = $id;
        $this->editingItem = $item->toArray();
        $this->iconSearch  = '';
        $this->activeTab   = $item->icon_type ?? 'flux';
        $this->imageUpload = null;
        $this->showIconPicker = true;
    }

    public function selectIcon(string $icon): void
    {
        if (!$this->editingId) return;
        
        $item = NavigationItem::find($this->editingId);
        
        // Hapus gambar lama jika sebelumnya bertipe image
        if ($item->icon_type === 'image' && $item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->update([
            'icon_type'  => 'flux',
            'icon'       => $icon,
            'image_path' => null,
        ]);
        
        $this->showIconPicker = false;
        $this->editingId = null;
        $this->dispatch('$refresh');
        \Flux::toast('Icon berhasil diperbarui.', variant: 'success');
    }

    public function saveImage(): void
    {
        $this->validate([
            'imageUpload' => 'image|max:1024', // max 1MB
        ]);

        if (!$this->editingId || !$this->imageUpload) return;
        
        $item = NavigationItem::find($this->editingId);
        
        // Hapus gambar lama jika ada
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        // Simpan gambar baru
        $path = $this->imageUpload->store('nav-icons', 'public');

        $item->update([
            'icon_type'  => 'image',
            'image_path' => $path,
        ]);
        
        $this->showIconPicker = false;
        $this->editingId = null;
        $this->imageUpload = null;
        $this->dispatch('$refresh');
        \Flux::toast('Gambar berhasil diunggah.', variant: 'success');
    }

    public function updateLabel(int $id, string $label): void
    {
        NavigationItem::find($id)?->update(['label' => $label]);
    }

    public function toggleActive(int $id): void
    {
        $item = NavigationItem::find($id);
        if (!$item) return;
        $item->update(['is_active' => !$item->is_active]);
    }

    public function updateSortOrder(int $id, int $order): void
    {
        NavigationItem::find($id)?->update(['sort_order' => $order]);
    }
};
?>

<x-pages::settings.layout heading="Navigation Icons" subheading="Kelola ikon dan label untuk setiap menu di sidebar dan dock mobile.">
    {{-- Groups --}}
    <div class="space-y-8 min-w-[700px] -ml-4 mt-6">
        @foreach($this->groupedItems as $section => $items)
        <div>
            <h3 class="text-xs font-bold text-zinc-400 uppercase tracking-widest mb-3">{{ $section }}</h3>
            <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 divide-y divide-zinc-100 dark:divide-zinc-800 overflow-hidden shadow-sm">
                @foreach($items as $item)
                <div class="flex items-center gap-4 px-4 py-3" wire:key="nav-{{ $item->id }}">
                    {{-- Icon Preview + Picker --}}
                    <button wire:click="openIconPicker({{ $item->id }})"
                            title="Klik untuk ganti icon"
                            class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-all
                                   {{ $item->is_active
                                       ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/40'
                                       : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}">
                        @if($item->icon_type === 'image' && $item->image_path)
                            <img src="{{ Storage::url($item->image_path) }}" class="w-5 h-5 object-contain" />
                        @else
                            <flux:icon :icon="$item->icon" class="w-5 h-5" />
                        @endif
                    </button>

                    {{-- Label --}}
                    <div class="flex-1 min-w-0">
                        <input type="text"
                               value="{{ $item->label }}"
                               wire:change="updateLabel({{ $item->id }}, $event.target.value)"
                               class="w-full bg-transparent text-sm font-medium text-zinc-800 dark:text-zinc-200 border-0 border-b border-transparent hover:border-zinc-200 dark:hover:border-zinc-700 focus:border-indigo-400 focus:ring-0 px-0 py-0.5 transition-colors outline-none"
                        />
                        <p class="text-[11px] text-zinc-400 font-mono mt-0.5">{{ $item->route_name }}</p>
                    </div>

                    {{-- Sort Order --}}
                    <input type="number" inputmode="numeric" pattern="[0-9]*" value="{{ $item->sort_order }}"
                           wire:change="updateSortOrder({{ $item->id }}, $event.target.value)"
                           class="w-14 text-center text-xs bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg py-1 focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 outline-none text-zinc-600 dark:text-zinc-400"
                           title="Urutan"
                    />

                    {{-- Permission badge --}}
                    @if($item->permission)
                        <span class="hidden lg:block text-[10px] text-zinc-400 font-mono bg-zinc-50 dark:bg-zinc-800 px-2 py-1 rounded-lg border border-zinc-200 dark:border-zinc-700 max-w-[140px] truncate">
                            {{ $item->permission }}
                        </span>
                    @endif

                    {{-- Active toggle --}}
                    <button wire:click="toggleActive({{ $item->id }})"
                            class="shrink-0 w-10 h-6 rounded-full transition-colors relative
                                   {{ $item->is_active ? 'bg-indigo-600' : 'bg-zinc-200 dark:bg-zinc-700' }}">
                        <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform
                                     {{ $item->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                    </button>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- Icon Picker Modal --}}
    @if($showIconPicker)
    <div class="fixed inset-0 z-[10100] flex items-end sm:items-center justify-center p-4"
         x-data x-on:keydown.escape.window="$wire.showIconPicker = false">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/40" wire:click="$set('showIconPicker', false)"></div>

        {{-- Panel --}}
        <div class="relative z-10 w-full max-w-sm bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden flex flex-col">
            <div class="px-4 pt-4 pb-0 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600">
                            @if(($editingItem['icon_type'] ?? 'flux') === 'image' && ($editingItem['image_path'] ?? null))
                                <img src="{{ Storage::url($editingItem['image_path']) }}" class="w-4 h-4 object-contain" />
                            @else
                                <flux:icon :icon="$editingItem['icon'] ?? 'link'" class="w-4 h-4" />
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $editingItem['label'] ?? '' }}</p>
                            <p class="text-[11px] text-zinc-400">Pilih ikon</p>
                        </div>
                    </div>
                    <button wire:click="$set('showIconPicker', false)" class="p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-400">
                        <flux:icon.x-mark class="w-4 h-4" />
                    </button>
                </div>
                
                <div class="flex items-center gap-4 border-b border-transparent mt-2">
                    <button wire:click="$set('activeTab', 'flux')" class="text-xs font-medium pb-2 border-b-2 transition-colors {{ $activeTab === 'flux' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">Icon Bawaan</button>
                    <button wire:click="$set('activeTab', 'image')" class="text-xs font-medium pb-2 border-b-2 transition-colors {{ $activeTab === 'image' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">Upload Gambar</button>
                </div>
            </div>

            @if($activeTab === 'flux')
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50">
                <input wire:model.live="iconSearch"
                       type="text"
                       placeholder="Cari icon..."
                       class="w-full text-sm bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-400 outline-none text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 mb-3"
                />
                <div class="grid grid-cols-6 gap-1.5 max-h-64 overflow-y-auto pr-1">
                    @foreach($this->filteredIcons as $icon)
                    <button wire:click="selectIcon('{{ $icon }}')"
                            title="{{ $icon }}"
                            class="aspect-square rounded-xl flex items-center justify-center transition-all
                                   {{ ($editingItem['icon_type'] ?? 'flux') === 'flux' && ($editingItem['icon'] ?? '') === $icon
                                       ? 'bg-indigo-600 text-white shadow-md'
                                       : 'hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-400' }}">
                        <flux:icon :icon="$icon" class="w-5 h-5" />
                    </button>
                    @endforeach
                </div>
            </div>
            @else
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 min-h-[200px] flex flex-col items-center justify-center">
                @if ($imageUpload)
                    <div class="relative mb-4">
                        <img src="{{ $imageUpload->temporaryUrl() }}" class="w-16 h-16 object-contain rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2 shadow-sm" />
                        <button wire:click="$set('imageUpload', null)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow-sm hover:bg-red-600 transition-colors">
                            <flux:icon.x-mark class="w-3 h-3" />
                        </button>
                    </div>
                    <button wire:click="saveImage" wire:loading.attr="disabled" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition-colors flex items-center gap-2">
                        <span wire:loading.remove wire:target="saveImage">Simpan Gambar</span>
                        <span wire:loading wire:target="saveImage">Menyimpan...</span>
                    </button>
                @else
                    <label class="w-full h-32 border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-2xl flex flex-col items-center justify-center text-zinc-500 dark:text-zinc-400 hover:border-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/10 hover:text-indigo-600 transition-all cursor-pointer">
                        <flux:icon.photo class="w-8 h-8 mb-2 opacity-50" />
                        <span class="text-sm font-medium">Klik untuk upload logo</span>
                        <span class="text-[10px] opacity-70 mt-1">PNG, JPG up to 1MB. (Rasio 1:1)</span>
                        <input type="file" wire:model="imageUpload" accept="image/*" class="hidden" />
                    </label>
                @endif
                
                <div wire:loading wire:target="imageUpload" class="mt-3 text-xs text-indigo-600 font-medium">
                    Mengunggah gambar...
                </div>
                
                @error('imageUpload')
                    <span class="mt-3 text-xs text-red-500 font-medium">{{ $message }}</span>
                @enderror
            </div>
            @endif
        </div>
    </div>
    @endif
</x-pages::settings.layout>
