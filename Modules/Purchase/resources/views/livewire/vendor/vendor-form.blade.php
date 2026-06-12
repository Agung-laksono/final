<?php
use function Livewire\Volt\{state, on, uses, computed};
use Modules\Purchase\Models\Vendor;
use Livewire\WithFileUploads;

uses([WithFileUploads::class]);

$existingTypes = computed(function () {
    return Vendor::whereNotNull('type')
        ->where('type', '!=', '')
        ->groupBy('type')
        ->get()
        ->pluck('type');
});

state([
    'vendor_id' => null,
    'name' => '',
    'phone' => '',
    'address' => '',
    'province' => '',
    'city' => '',
    'district' => '',
    'village' => '',
    'vendor_type' => 'Supplier',
    'image' => null,
    'existing_image' => null,
]);

$save = function () {
    abort_unless(auth()->user()->can($this->vendor_id ? 'purchase.update' : 'purchase.create'), 403, 'Tidak ada izin menyimpan data vendor.');
    
    $this->validate([
        'name' => 'required|string|max:255',
        'phone' => 'nullable|string|max:50',
        'vendor_type' => 'required|string|max:100',
        // Hapus validasi |image karena cropper mengembalikan string base64
    ]);

    $imagePath = $this->existing_image;
    
    if ($this->image && is_string($this->image) && str_starts_with($this->image, 'data:image')) {
        // Decode base64 gambar yang dicrop
        $imageParts = explode(";base64,", $this->image);
        $imageBase64 = base64_decode($imageParts[1]);
        $fileName = 'vendors/' . uniqid() . '.webp';
        Storage::disk('public')->put($fileName, $imageBase64);
        $imagePath = $fileName;
    } elseif ($this->image && !is_string($this->image)) {
        $imagePath = $this->image->store('vendors', 'public');
    }

    Vendor::updateOrCreate(
        ['id' => $this->vendor_id],
        [
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'village' => $this->village,
            'type' => $this->vendor_type,
            'image' => $imagePath,
        ]
    );

    $this->dispatch('vendor-saved');
    Flux::modal('vendor-form-modal')->close();
    
    $this->reset();
};

on(['edit-vendor' => function ($id) {
    // Jaga-jaga jika Livewire mengirimkan array/object
    $vendorId = is_array($id) ? ($id['id'] ?? null) : $id;
    
    $vendor = Vendor::find($vendorId);
    if ($vendor) {
        $this->vendor_id = $vendor->id;
        $this->name = $vendor->name;
        $this->phone = $vendor->phone;
        $this->address = $vendor->address;
        $this->province = $vendor->province;
        $this->city = $vendor->city;
        $this->district = $vendor->district;
        $this->village = $vendor->village;
        $this->vendor_type = $vendor->type;
        $this->existing_image = $vendor->image;
        $this->image = null;
        
        Flux::modal('vendor-form-modal')->show();
    }
}]);

$resetForm = function () {
    $this->reset();
};
?>

<div>
    @can('purchase.create')
        <flux:modal.trigger name="vendor-form-modal">
            <flux:button variant="primary" icon="plus" wire:click="resetForm">Tambah Vendor</flux:button>
        </flux:modal.trigger>
    @endcan

    <flux:modal name="vendor-form-modal" class="md:w-[500px] space-y-6">
        <div>
            <flux:heading size="lg">{{ $vendor_id ? 'Edit Vendor' : 'Tambah Vendor Baru' }}</flux:heading>
            <flux:subheading>Masukkan informasi detail mengenai supplier/vendor.</flux:subheading>
        </div>

        <form wire:submit="save" class="space-y-4">
            {{-- Foto/Logo Menggunakan Image Cropper Global --}}
            <div class="w-full sm:w-1/2">
                <flux:label class="mb-2">Logo / Foto Vendor</flux:label>
                <x-image-cropper wire:model="image" :image="$existing_image" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="name" label="Nama Perusahaan/Vendor" placeholder="PT. Angkasa Raya" required />
                <flux:input wire:model="phone" label="No. Telepon" placeholder="0812..." />
            </div>
            
            <flux:textarea wire:model="address" label="Alamat Detail" placeholder="Jl. Sudirman No. 1..." />
            
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="province" label="Provinsi" />
                <flux:input wire:model="city" label="Kota/Kabupaten" />
                <flux:input wire:model="district" label="Kecamatan" />
                <flux:input wire:model="village" label="Desa/Kelurahan" />
            </div>

            <div wire:key="vendor-type-wrapper-{{ $vendor_id ?? 'new' }}" x-data="{ editingType: {{ $vendor_id ? 'false' : 'true' }} }">
                <flux:label class="mb-2">Tipe Vendor</flux:label>

                {{-- Mode ReadOnly (Hanya muncul saat edit) --}}
                <div x-show="!editingType" x-on:click="editingType = true" class="cursor-pointer group relative" title="Klik untuk mengubah tipe">
                    <flux:input value="{{ $vendor_type }}" disabled class="cursor-pointer bg-zinc-50 dark:bg-zinc-800" />
                    <div class="absolute inset-y-0 right-3 flex items-center">
                        <flux:icon.pencil-square class="w-4 h-4 text-zinc-400 group-hover:text-blue-500 transition-colors" />
                    </div>
                </div>

                {{-- Mode Edit (Datalist) --}}
                <div x-show="editingType" x-cloak>
                    <input type="text" wire:model="vendor_type" list="vendor-types-list" required 
                        placeholder="Ketik tipe baru atau pilih dari saran..."
                        class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-blue-500 focus:ring-blue-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:placeholder-zinc-500 dark:focus:border-blue-500 dark:focus:ring-blue-500" />
                    <datalist id="vendor-types-list">
                        @foreach($this->existingTypes as $existingType)
                            <option value="{{ $existingType }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <flux:error name="vendor_type" />
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
