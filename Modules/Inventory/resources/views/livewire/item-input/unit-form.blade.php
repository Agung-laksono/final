<?php

use function Livewire\Volt\{state, rules, on};
use Modules\Inventory\Models\Unit;
use Flux\Flux;

// State untuk form dan data
state([
    'unit_id' => null, // Jika null berarti "Tambah Baru", jika ada isinya berarti "Edit"
    'name' => '',
    'symbol' => '',
    'units' => fn () => Unit::latest()->get(),
    'show' => fn () => request()->routeIs('inventory-settings')
]);

// Aturan validasi
rules(fn () => [
    'name' => 'required|string|max:255|unique:units,name,' . $this->unit_id,
    'symbol' => 'nullable|string|max:10',
]);

// Fungsi untuk membuka modal (bisa untuk Tambah Baru atau Edit)
$openModal = function ($id = null) {
    $this->resetValidation();
    
    if ($id) {
        $unit = Unit::findOrFail($id);
        $this->unit_id = $unit->id;
        $this->name = $unit->name;
        $this->symbol = $unit->symbol;
    } else {
        $this->unit_id = null;
        $this->name = '';
        $this->symbol = '';
    }
    
    Flux::modal('unit-modal')->show();
};

// Listener agar komponen lain (misal: halaman form barang) bisa memicu modal ini
on(['open-unit-modal' => function ($id = null) {
    $this->openModal($id);
}]);

// Fungsi untuk menyimpan (Create / Update)
$save = function () {
    $this->validate();

    if ($this->unit_id) {
        // Mode Edit
        $savedUnit = Unit::findOrFail($this->unit_id);
        $savedUnit->update([
            'name' => $this->name,
            'symbol' => $this->symbol,
        ]);
    } else {
        // Mode Tambah Baru
        $savedUnit = Unit::create([
            'name' => $this->name,
            'symbol' => $this->symbol,
        ]);
    }

    // Refresh data & tutup modal
    $this->units = Unit::latest()->get();
    
    // Memberi tahu (dispatch) komponen induk beserta ID unit yang baru dan daftar opsi
    $this->dispatch('unit-updated', id: $savedUnit->id, options: Unit::orderBy('name')->get());
    
    Flux::modal('unit-modal')->close();
};

// Fungsi untuk menghapus data
$delete = function (Unit $unit) {
    $unit->delete();
    $this->units = Unit::latest()->get(); // Refresh
    
    // Memberi tahu (dispatch) komponen induk bahwa ada perubahan data unit
    $this->dispatch('unit-updated');
};

?>

<div x-on:open-unit-modal.window="$wire.openModal()">
    @if ($show)
    {{-- Header & Tombol Tambah --}}
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="lg">Pengelolaan Satuan (Unit)</flux:heading>
        <flux:button wire:click="openModal" variant="primary" icon="plus">Tambah Satuan</flux:button>
    </div>

    {{-- Daftar Data yang sudah diinput --}}
    <div class="mt-4">
        <div class="space-y-2">
            @forelse($units as $unit)
                <div class="flex items-center justify-between p-4 bg-white/50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-xl backdrop-blur-sm">
                    <div class="flex flex-col">
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $unit->name }}</span>
                        @if($unit->symbol)
                            <span class="text-sm text-zinc-500">Simbol: <flux:badge size="sm" color="zinc">{{ $unit->symbol }}</flux:badge></span>
                        @endif
                    </div>
                    
                    {{-- Action Buttons --}}
                    <div class="flex gap-2">
                        <flux:button wire:click="openModal({{ $unit->id }})" variant="ghost" size="sm" icon="pencil" class="text-blue-500 hover:text-blue-700" />
                        <flux:button wire:click="delete({{ $unit->id }})" wire:confirm="Yakin ingin menghapus satuan {{ $unit->name }}?" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-700" />
                    </div>
                </div>
            @empty
                <div class="text-sm text-zinc-500 text-center p-6 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl">
                    Belum ada data satuan yang ditambahkan. Klik tombol di atas untuk menambah.
                </div>
            @endforelse
        </div>
    </div>
    @endif

    {{-- Modal Form (Otomatis menyesuaikan mode Tambah/Edit) --}}
    <flux:modal name="unit-modal" class="md:w-96 space-y-6">
        <div>
            <flux:heading size="lg">{{ $unit_id ? 'Edit Satuan' : 'Tambah Satuan Baru' }}</flux:heading>
            <flux:subheading>Pastikan nama satuan jelas dan mudah dipahami.</flux:subheading>
        </div>

        <form wire:submit="save" class="space-y-4">
            <flux:input wire:model="name" label="Nama Satuan" placeholder="Contoh: Kilogram, Pieces" required />
            <flux:input wire:model="symbol" label="Simbol (Opsional)" placeholder="Contoh: Kg, Pcs" />
            
            <div class="flex justify-end gap-2 mt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $unit_id ? 'Simpan Perubahan' : 'Tambahkan' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
