<?php

use function Livewire\Volt\{state, on, with};
use App\Models\User;
use Spatie\Permission\Models\Role;
use Livewire\Attributes\Validate;

state([
    'userId' => null,
    'user' => null,
    'selectedRoles' => [],
    'selectedWarehouses' => [],
    'selectedBrand' => null,
]);

$getAvailableRoles = function () {
    return Role::pluck('name')->toArray();
};

$getAvailableWarehouses = function () {
    return \Modules\Inventory\Models\Warehouse::all();
};

$getAvailableBrands = function () {
    return \App\Models\Brand::all();
};

on(['open-assign-role' => function (int $userId) {
    $this->userId = $userId;
    $this->user = User::with(['roles', 'warehouses', 'brand'])->find($this->userId);
    
    if ($this->user) {
        $this->selectedRoles = $this->user->roles->pluck('name')->toArray();
        $this->selectedWarehouses = $this->user->warehouses->pluck('id')->toArray();
        $this->selectedBrand = $this->user->brand_id;
        \Flux::modal('assign-role-modal')->show();
    }
}]);

$save = function () {
    abort_if(auth()->user()->cannot('users.update'), 403);

    if (!$this->userId) return;
    $user = User::find($this->userId);
    if (!$user) return;
    
    // Mencegah menghapus akses Super Admin jika itu akun terakhir, dll
    // Tapi untuk kesederhanaan, kita langsung sync:
    $user->syncRoles($this->selectedRoles);
    $user->warehouses()->sync($this->selectedWarehouses);
    $user->update(['brand_id' => $this->selectedBrand ?: null]);
    
    \Flux::toast('Hak akses & penugasan berhasil diperbarui!');
    
    \Flux::modal('assign-role-modal')->close();
    $this->dispatch('role-updated');
};

?>

<flux:modal name="assign-role-modal" class="md:w-96">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Ubah Hak Akses & Lokasi</flux:heading>
            <flux:subheading>
                Tentukan wewenang dan wilayah Gudang yang dikelola.
            </flux:subheading>
        </div>

        @if($user)
            <div class="flex items-center gap-3 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                <flux:avatar size="sm" :initials="$user->initials()" :src="$user->avatarUrl()" />
                <div class="flex flex-col">
                    <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $user->name }}</span>
                    <span class="text-xs text-zinc-500">{{ $user->email }}</span>
                </div>
            </div>

            <div class="space-y-4 pt-2">
                <flux:checkbox.group wire:model="selectedRoles" label="Jabatan (Role)">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($this->getAvailableRoles() as $role)
                            <flux:checkbox :value="$role" :label="$role" />
                        @endforeach
                    </div>
                </flux:checkbox.group>

                <flux:separator variant="subtle" />

                <flux:checkbox.group wire:model="selectedWarehouses" label="Penugasan Gudang">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($this->getAvailableWarehouses() as $wh)
                            <flux:checkbox :value="$wh->id" :label="$wh->name" />
                        @endforeach
                    </div>
                </flux:checkbox.group>

                <flux:separator variant="subtle" />

                <flux:select wire:model="selectedBrand" label="Penugasan Brand (Khusus Sales)">
                    <option value="">-- Tidak Ada Brand --</option>
                    @foreach($this->getAvailableBrands() as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        <div class="flex mt-6 gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost"> Batal </flux:button>
            </flux:modal.close>
            <flux:button icon="check" wire:click="save" wire:target="save" wire:loading.attr="disabled" variant="primary"> Simpan Role </flux:button>
        </div>
    </div>
</flux:modal>
