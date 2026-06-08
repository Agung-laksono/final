<?php

use function Livewire\Volt\{state, on, usesFileUploads};
use Modules\Inventory\Models\Warehouse;
use Livewire\WithFileUploads;
use Flux\Flux;

usesFileUploads();

state([
    'warehouseId' => null,
    'code' => '',
    'name' => '',
    'address' => '',
    'image' => null,
    'existingImage' => null,
]);

$resetForm = function () {
    $this->warehouseId = null;
    $this->code = '';
    $this->name = '';
    $this->address = '';
    $this->image = null;
    $this->existingImage = null;
    $this->resetValidation();
};

on(['open-warehouse-form' => function ($id = null) {
    $this->resetForm();
    if ($id) {
        $warehouse = Warehouse::findOrFail($id);
        $this->warehouseId = $warehouse->id;
        $this->code = $warehouse->code;
        $this->name = $warehouse->name;
        $this->address = $warehouse->address;
        $this->existingImage = $warehouse->image;
    }
    // Paksa reset state cropper setiap kali modal dibuka
    $this->dispatch('reset-cropper'); 
    Flux::modal('warehouse-modal')->show();
}]);

$save = function () {
    $rules = [
        'name' => 'required|string|max:255',
        'code' => 'required|string|max:255|unique:warehouses,code,' . $this->warehouseId,
        'address' => 'nullable|string',
        'image' => 'nullable|string', // Base64 dari cropper
    ];

    $validated = $this->validate($rules);

    // Handle Image Upload (Base64 dari Cropper)
    $imagePath = $this->existingImage;
    if ($this->image && str_starts_with($this->image, 'data:image')) {
        if ($this->existingImage) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($this->existingImage);
        }
        
        // Decode Base64 string
        $imageParts = explode(';base64,', $this->image);
        $imageBase64 = base64_decode($imageParts[1]);
        $fileName = 'warehouses/' . \Illuminate\Support\Str::random(40) . '.webp';
        
        \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $imageBase64);
        $imagePath = $fileName;
    }

    if ($this->warehouseId) {
        $warehouse = Warehouse::findOrFail($this->warehouseId);
        $warehouse->update([
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'image' => $imagePath,
        ]);
        $message = 'Gudang berhasil diperbarui.';
    } else {
        Warehouse::create([
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'image' => $imagePath,
        ]);
        $message = 'Gudang baru berhasil ditambahkan.';
    }

    $this->dispatch('warehouse-saved');
    $this->dispatch('reset-cropper'); // Bersihkan cropper setelah simpan
    Flux::modal('warehouse-modal')->close();
    $this->resetForm();
    
    // Todo: Dispatch toast notification
};

on(['delete-warehouse' => function ($data) {
    if (isset($data['id'])) {
        $warehouse = Warehouse::findOrFail($data['id']);
        
        // Prevent deletion if there are items with stock > 0 in this warehouse?
        // For now, we'll just delete (or let constraint handle it if we want to)
        // Wait, the pivot item_warehouse cascades delete on warehouse.
        // It's better to verify if it's safe. For MVP, we will allow it, but we can add validation later.
        
        if ($warehouse->image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($warehouse->image);
        }
        
        $warehouse->delete();
        $this->dispatch('warehouse-deleted');
    }
}]);

?>

<div>
    <flux:modal name="warehouse-modal" class="md:max-w-xl" style="max-width: 95vw;" scorll="body">
        <form wire:submit="save" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $warehouseId ? 'Edit Gudang' : 'Tambah Gudang Baru' }}</flux:heading>
                <flux:subheading>Masukkan rincian informasi gudang tempat penyimpanan stok.</flux:subheading>
            </div>

            <div class="space-y-5">
                
                {{-- Foto Gudang --}}
                <x-image-cropper wire:model="image" :image="$existingImage" label="Foto Gudang (Crop Interaktif)" reset-event="reset-cropper" />

                {{-- Kode & Nama --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input wire:model="code" label="Kode Gudang" placeholder="Contoh: GDG-PST" required />
                    <flux:input wire:model="name" label="Nama Gudang" placeholder="Contoh: Gudang Pusat" required />
                </div>

                {{-- Alamat --}}
                <flux:textarea wire:model="address" label="Alamat Gudang" placeholder="Tuliskan alamat lengkap gudang (opsional)..." rows="3" />

            </div>

            <div class="flex justify-end gap-2 mt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan Gudang</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
