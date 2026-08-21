<?php

use function Livewire\Volt\{state, on};
use Spatie\Permission\Models\Role;
use Livewire\Attributes\Validate;

state([
    'newRoleName' => '',
    'editRoleId' => null,
    'editRoleName' => ''
]);

$getRoles = function () {
    return Role::where('name', '!=', 'Super Admin')
        ->withCount('permissions')
        ->get();
};

$createRole = function () {
    $this->validate([
        'newRoleName' => 'required|string|max:255|unique:roles,name'
    ]);

    Role::create(['name' => $this->newRoleName]);
    
    $this->newRoleName = '';
    \Flux::toast('Jabatan baru berhasil ditambahkan!');
    \Flux::modal('create-role-modal')->close();
};

$openEditModal = function ($id) {
    $role = Role::findOrFail($id);
    $this->editRoleId = $role->id;
    $this->editRoleName = $role->name;
    \Flux::modal('edit-role-modal')->show();
};

$updateRole = function () {
    $this->validate([
        'editRoleName' => 'required|string|max:255|unique:roles,name,' . $this->editRoleId
    ]);

    $role = Role::findOrFail($this->editRoleId);
    $role->update(['name' => $this->editRoleName]);
    
    \Flux::toast('Nama jabatan berhasil diperbarui!');
    \Flux::modal('edit-role-modal')->close();
};

on(['permissions-updated' => function () {
    // Re-render
}]);

?>

<div>
    <div class="mb-6 flex justify-between items-center">
        <div>
            <flux:heading size="lg">Jabatan & Wewenang</flux:heading>
            <flux:subheading>Kelola daftar jabatan (Role) dan wewenang (Permissions) khusus untuk tiap jabatan.</flux:subheading>
        </div>
        <flux:button x-on:click="$flux.modal('create-role-modal').show()" variant="primary" icon="plus">Tambah Jabatan</flux:button>
    </div>

    <div class="space-y-2">
        @forelse ($this->getRoles() as $role)
            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-700/50">
                <div class="min-w-0">
                    <p class="font-medium text-sm text-zinc-900 dark:text-zinc-100 truncate">{{ $role->name }}</p>
                    <flux:badge size="sm" class="mt-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                        {{ $role->permissions_count }} Hak Akses
                    </flux:badge>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <flux:button size="sm" wire:click="openEditModal({{ $role->id }})" variant="ghost" icon="pencil-square" class="text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100">
                        Edit
                    </flux:button>
                    <flux:button size="sm" x-on:click="$dispatch('open-role-permissions', { roleId: {{ $role->id }} })" variant="ghost" icon="cog-8-tooth" class="text-blue-600 hover:text-blue-700">
                        Akses
                    </flux:button>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-10 text-zinc-400">
                <flux:icon.shield-check class="w-10 h-10 mb-2 text-zinc-300" />
                <p class="text-sm">Belum ada jabatan khusus.</p>
            </div>
        @endforelse
    </div>

    {{-- Modal Tambah Jabatan --}}
    <template x-teleport="body">
        <flux:modal name="create-role-modal" class="md:w-96">
            <form wire:submit="createRole" class="space-y-6">
                <div>
                    <flux:heading size="lg">Tambah Jabatan Baru</flux:heading>
                    <flux:subheading>
                        Buat peran baru seperti "Kepala Gudang" atau "Kurir".
                    </flux:subheading>
                </div>

                <flux:input wire:model="newRoleName" label="Nama Jabatan" placeholder="Contoh: Kepala Gudang" required />

                <div class="flex mt-6 gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost"> Batal </flux:button>
                    </flux:modal.close>
                    <flux:button icon="check" type="submit" variant="primary"> Simpan </flux:button>
                </div>
            </form>
        </flux:modal>
    </template>

    {{-- Modal Edit Jabatan --}}
    <template x-teleport="body">
        <flux:modal name="edit-role-modal" class="md:w-96">
            <form wire:submit="updateRole" class="space-y-6">
                <div>
                    <flux:heading size="lg">Edit Jabatan</flux:heading>
                    <flux:subheading>
                        Ubah nama jabatan ini.
                    </flux:subheading>
                </div>

                <flux:input wire:model="editRoleName" label="Nama Jabatan" required />

                <div class="flex mt-6 gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost"> Batal </flux:button>
                    </flux:modal.close>
                    <flux:button icon="check" type="submit" variant="primary"> Simpan </flux:button>
                </div>
            </form>
        </flux:modal>
    </template>

    {{-- Modal Atur Permissions --}}
    <livewire:settings.role-permissions-modal />
</div>
