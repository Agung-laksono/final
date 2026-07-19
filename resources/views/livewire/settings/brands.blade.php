<?php

use Livewire\Volt\Component;
use App\Models\Brand;
use Modules\Finance\Models\FinanceAccount;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public $brands = [];
    public $accounts = [];
    
    // Form state
    public $showModal = false;
    public $brandId = null;
    public $name = '';
    public $tagline = '';
    public $address = '';
    public $phone = '';
    public $email = '';
    public $website = '';
    public $npwp = '';
    public $director_name = '';
    public $selectedAccounts = []; // Array of finance_account IDs
    public $logo;
    public $currentLogo = null;
    public $signature_image;
    public $currentSignatureImage = null;
    public $stamp_image;
    public $currentStampImage = null;
    
    public $showDeleteModal = false;
    public $brandToDelete = null;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->brands = Brand::with(['financeAccounts', 'users'])->get();
        $this->accounts = FinanceAccount::where('is_active', true)->get();
    }

    public function createBrand()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function editBrand($id)
    {
        $this->resetForm();
        $brand = Brand::with('financeAccounts')->find($id);
        if ($brand) {
            $this->brandId = $brand->id;
            $this->name = $brand->name;
            $this->tagline = $brand->tagline;
            $this->address = $brand->address;
            $this->phone = $brand->phone;
            $this->email = $brand->email;
            $this->website = $brand->website;
            $this->npwp = $brand->npwp;
            $this->director_name = $brand->director_name;
            $this->selectedAccounts = $brand->financeAccounts->pluck('id')->map(fn($id) => (string)$id)->toArray();
            $this->currentLogo = $brand->logo;
            $this->currentSignatureImage = $brand->signature_image;
            $this->currentStampImage = $brand->stamp_image;
            $this->showModal = true;
        }
    }

    public function saveBrand()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'npwp' => 'nullable|string|max:50',
            'director_name' => 'nullable|string|max:255',
            'selectedAccounts' => 'nullable|array',
            'selectedAccounts.*' => 'exists:finance_accounts,id',
            'logo' => 'nullable',
            'signature_image' => 'nullable',
            'stamp_image' => 'nullable',
        ]);

        $data = [
            'name' => $this->name,
            'tagline' => $this->tagline,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'npwp' => $this->npwp,
            'director_name' => $this->director_name,
        ];

        $processImage = function($imageProp, $currentImage, $prefix) use (&$data) {
            if ($this->$imageProp) {
                if ($this->brandId && $currentImage) {
                    Storage::disk('public')->delete($currentImage);
                }
                
                if (is_string($this->$imageProp) && str_starts_with($this->$imageProp, 'data:image')) {
                    list($type, $imgData) = explode(';', $this->$imageProp);
                    list(, $imgData)      = explode(',', $imgData);
                    $imgData = base64_decode($imgData);
                    
                    $filename = 'brands/' . $prefix . '_' . uniqid() . '.webp';
                    Storage::disk('public')->put($filename, $imgData);
                    $data[$imageProp] = $filename;
                } elseif (is_object($this->$imageProp)) {
                    $data[$imageProp] = $this->$imageProp->store('brands', 'public');
                }
            }
        };

        $processImage('logo', $this->currentLogo, 'logo');
        $processImage('signature_image', $this->currentSignatureImage, 'sig');
        $processImage('stamp_image', $this->currentStampImage, 'stamp');

        if ($this->brandId) {
            $brand = Brand::find($this->brandId);
            $brand->update($data);
            // Sync many-to-many
            $brand->financeAccounts()->sync($this->selectedAccounts);
            \Flux::toast('Brand berhasil diperbarui.', variant: 'success');
        } else {
            $brand = Brand::create($data);
            $brand->financeAccounts()->sync($this->selectedAccounts);
            \Flux::toast('Brand berhasil ditambahkan.', variant: 'success');
        }

        $this->showModal = false;
        $this->loadData();
    }

    public function confirmDelete($id)
    {
        $this->brandToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function deleteBrand()
    {
        if ($this->brandToDelete) {
            $brand = Brand::find($this->brandToDelete);
            if ($brand) {
                if ($brand->logo) Storage::disk('public')->delete($brand->logo);
                if ($brand->signature_image) Storage::disk('public')->delete($brand->signature_image);
                if ($brand->stamp_image) Storage::disk('public')->delete($brand->stamp_image);
                $brand->financeAccounts()->detach();
                $brand->delete();
                \Flux::toast('Brand berhasil dihapus.', variant: 'success');
            }
        }
        $this->showDeleteModal = false;
        $this->loadData();
    }

    public function resetForm()
    {
        $this->reset(['brandId', 'name', 'tagline', 'address', 'phone', 'email', 'website', 'npwp', 'director_name', 'selectedAccounts', 'logo', 'currentLogo', 'signature_image', 'currentSignatureImage', 'stamp_image', 'currentStampImage']);
        $this->selectedAccounts = [];
        $this->resetValidation();
    }
};
?>
<x-pages::settings.layout :heading="__('Manajemen Brand')" :subheading="__('Kelola daftar brand, logo, dan rekening penerimaannya.')">
    <div>
        <div class="mb-6 flex justify-end">
            <flux:button variant="primary" wire:click="createBrand" icon="plus">Tambah Brand</flux:button>
        </div>

        <div class="grid grid-cols-1 gap-6">
            @forelse($brands as $brand)
                <flux:card class="flex flex-col">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-4">
                            @if($brand->logo)
                                <img src="{{ Storage::url($brand->logo) }}" alt="{{ $brand->name }}" class="w-16 h-16 object-contain rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white">
                            @else
                                <div class="w-16 h-16 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500 border border-zinc-200 dark:border-zinc-700">
                                    <flux:icon.building-storefront class="w-8 h-8" />
                                </div>
                            @endif
                            <div>
                                <div class="font-semibold text-lg">{{ $brand->name }}</div>
                                @if($brand->tagline)
                                    <div class="text-sm text-zinc-500">{{ $brand->tagline }}</div>
                                @endif
                            </div>
                        </div>
                        
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                            <flux:menu>
                                <flux:menu.item wire:click="editBrand({{ $brand->id }})" icon="pencil-square">Edit</flux:menu.item>
                                <flux:menu.item wire:click="confirmDelete({{ $brand->id }})" icon="trash" variant="danger">Hapus</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                    
                    <div class="flex-1 text-sm text-zinc-600 dark:text-zinc-400 mb-4 space-y-2">
                        @if($brand->address)
                            <div class="flex gap-2">
                                <flux:icon.map-pin class="w-4 h-4 mt-0.5 shrink-0" />
                                <span>{{ $brand->address }}</span>
                            </div>
                        @endif

                        {{-- Rekening Kas (multi) --}}
                        @if($brand->financeAccounts->count() > 0)
                            <div class="flex gap-2 items-start">
                                <flux:icon.banknotes class="w-4 h-4 mt-0.5 shrink-0" />
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($brand->financeAccounts as $acc)
                                        <span class="inline-flex items-center gap-1 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 rounded-md px-2 py-0.5 text-xs font-medium">
                                            {{ $acc->name }}
                                            <span class="text-emerald-500 opacity-70">({{ $acc->type }})</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="flex gap-2 text-amber-500">
                                <flux:icon.exclamation-triangle class="w-4 h-4 mt-0.5 shrink-0" />
                                <span>Rekening belum diatur</span>
                            </div>
                        @endif
                        
                        <div class="flex gap-2 mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                            <flux:icon.users class="w-4 h-4 mt-1 shrink-0" />
                            <div class="flex flex-wrap gap-2">
                                @forelse($brand->users as $user)
                                    <div class="flex items-center gap-1.5 bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded-md text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                        <flux:avatar size="xs" :initials="$user->initials()" :src="$user->avatarUrl()" class="w-4 h-4" />
                                        {{ $user->name }}
                                    </div>
                                @empty
                                    <span class="text-zinc-400 italic mt-0.5">Belum ada staf yang ditugaskan</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </flux:card>
            @empty
                <div class="col-span-full py-12 text-center text-zinc-500 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl">
                    <flux:icon.building-storefront class="w-12 h-12 mx-auto mb-4 text-zinc-400" />
                    <p>Belum ada Brand yang ditambahkan.</p>
                    <flux:button variant="ghost" wire:click="createBrand" class="mt-4">Tambah Brand Pertama</flux:button>
                </div>
            @endforelse
        </div>

        <!-- Modal Form -->
        <flux:modal wire:model="showModal" class="md:w-[520px]">
            <form wire:submit="saveBrand">
                <div class="mb-6">
                    <flux:heading size="lg">{{ $brandId ? 'Edit Brand' : 'Tambah Brand Baru' }}</flux:heading>
                </div>
                
                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="name" label="Nama Brand" required />
                        <flux:input wire:model="tagline" label="Tagline / Slogan" />
                    </div>
                    
                    <flux:textarea wire:model="address" label="Alamat Lengkap" rows="2" />
                    
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="phone" label="No. Telepon / WA" />
                        <flux:input wire:model="email" label="Email" type="email" />
                        <flux:input wire:model="website" label="Website / Instagram" />
                        <flux:input wire:model="npwp" label="NPWP (Opsional)" />
                    </div>

                    <flux:input wire:model="director_name" label="Nama Pimpinan / Direktur" placeholder="Contoh: Budi Santoso" />

                    
                    {{-- Multi Rekening / Kas --}}
                    <div>
                        <flux:label class="mb-2">Rekening / Kas Penerima</flux:label>
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg divide-y divide-zinc-100 dark:divide-zinc-800 overflow-hidden">
                            @forelse($accounts as $acc)
                                <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <input
                                        type="checkbox"
                                        value="{{ $acc->id }}"
                                        wire:model="selectedAccounts"
                                        class="rounded border-zinc-300 text-cyan-600 focus:ring-cyan-500"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $acc->name }}</div>
                                        <div class="text-xs text-zinc-500">{{ $acc->type }}</div>
                                    </div>
                                    @if($acc->account_number)
                                        <span class="text-xs font-mono text-zinc-400">{{ $acc->account_number }}</span>
                                    @endif
                                </label>
                            @empty
                                <div class="px-4 py-3 text-sm text-zinc-500 text-center italic">
                                    Belum ada rekening aktif. Tambahkan di modul Finance terlebih dahulu.
                                </div>
                            @endforelse
                        </div>
                        <flux:error name="selectedAccounts" />
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        <div>
                            <flux:label class="mb-2">Logo Brand</flux:label>
                            <x-image-cropper id="brand-logo-cropper" wire:model="logo" :image="$logo && is_string($logo) && !str_starts_with($logo, 'data:image') ? $logo : ($currentLogo ? $currentLogo : null)" accept="image/*" />
                            <flux:error name="logo" />
                        </div>
                        <div>
                            <flux:label class="mb-2">Tanda Tangan</flux:label>
                            <x-image-cropper id="brand-sig-cropper" wire:model="signature_image" :image="$signature_image && is_string($signature_image) && !str_starts_with($signature_image, 'data:image') ? $signature_image : ($currentSignatureImage ? $currentSignatureImage : null)" accept="image/*" />
                            <flux:error name="signature_image" />
                        </div>
                        <div>
                            <flux:label class="mb-2">Stempel (Cap)</flux:label>
                            <x-image-cropper id="brand-stamp-cropper" wire:model="stamp_image" :image="$stamp_image && is_string($stamp_image) && !str_starts_with($stamp_image, 'data:image') ? $stamp_image : ($currentStampImage ? $currentStampImage : null)" accept="image/*" />
                            <flux:error name="stamp_image" />
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-2">
                    <flux:button variant="ghost" @click="$wire.showModal = false">Batal</flux:button>
                    <flux:button type="submit" variant="primary" wire:target="saveBrand" wire:loading.attr="disabled">Simpan</flux:button>
                </div>
            </form>
        </flux:modal>

        <!-- Modal Delete -->
        <flux:modal wire:model="showDeleteModal" class="md:w-[400px]">
            <div class="mb-6">
                <flux:heading size="lg">Hapus Brand</flux:heading>
                <flux:subheading>Apakah Anda yakin ingin menghapus brand ini? Data terkait pengguna yang memegang brand ini akan terpengaruh.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" @click="$wire.showDeleteModal = false">Batal</flux:button>
                <flux:button variant="danger" wire:click="deleteBrand">Hapus</flux:button>
            </div>
        </flux:modal>
    </div>
</x-pages::settings.layout>
