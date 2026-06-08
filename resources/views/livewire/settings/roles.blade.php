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

    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden mb-6 shadow-sm px-4 pb-2">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nama Jabatan</flux:table.column>
                <flux:table.column>Jumlah Wewenang</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->getRoles() as $role)
                    <flux:table.row :key="$role->id">
                        <flux:table.cell>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $role->name }}</span>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" class="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                {{ $role->permissions_count }} Hak Akses
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex gap-2 items-center">
                                <flux:button size="sm" wire:click="openEditModal({{ $role->id }})" variant="ghost" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100" icon="pencil-square">
                                    Edit
                                </flux:button>
                                <flux:button size="sm" x-on:click="$dispatch('open-role-permissions', { roleId: {{ $role->id }} })" variant="ghost" class="text-blue-600 hover:text-blue-700" icon="cog-8-tooth">
                                    Akses
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            <div class="flex flex-col items-center justify-center py-8 text-zinc-500">
                                <flux:icon.shield-check class="w-12 h-12 mb-3 text-zinc-300" />
                                <p>Belum ada jabatan khusus.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
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
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
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
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan Perubahan</flux:button>
                </div>
            </form>
        </flux:modal>
    </template>

    {{-- Modal Atur Permissions --}}
    <livewire:settings.role-permissions-modal />
</div>
