<?php

use function Livewire\Volt\{state, on, with, usesPagination};
use App\Models\User;
use Spatie\Permission\Models\Role;

usesPagination(theme: 'tailwind');

state([
    'search' => '',
    'perPage' => 10,
    
    // New User form state
    'newUserName' => '',
    'newUserEmail' => '',
    'newUserPassword' => '',
    'newUserPasswordConfirmation' => '',
    
    // Edit User form state
    'editUserId' => null,
    'editUserName' => '',
    'editUserEmail' => '',
    'editUserPassword' => '',
    'editUserPasswordConfirmation' => '',
]);

$getUsers = function () {
    return User::query()
        ->with('roles')
        ->when($this->search, function ($query) {
            $query->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
        })
        ->paginate($this->perPage);
};

$loadMore = function () {
    $this->perPage += 10;
};

$createUser = function () {
    abort_if(auth()->user()->cannot('users.create'), 403);

    $this->validate([
        'newUserName' => 'required|string|max:255',
        'newUserEmail' => 'required|string|email|max:255|unique:users,email',
        'newUserPassword' => 'required|string|min:8|same:newUserPasswordConfirmation',
    ]);

    User::create([
        'name' => $this->newUserName,
        'email' => $this->newUserEmail,
        'password' => \Illuminate\Support\Facades\Hash::make($this->newUserPassword),
    ]);
    
    $this->reset('newUserName', 'newUserEmail', 'newUserPassword', 'newUserPasswordConfirmation');
    
    \Flux::toast('Pengguna berhasil didaftarkan!', variant: 'success');
    \Flux::modal('create-user-modal')->close();
};

$editUser = function ($id) {
    abort_if(auth()->user()->cannot('users.update'), 403);
    $user = User::findOrFail($id);
    $this->editUserId = $user->id;
    $this->editUserName = $user->name;
    $this->editUserEmail = $user->email;
    $this->editUserPassword = '';
    $this->editUserPasswordConfirmation = '';
    $this->resetValidation();
    \Flux::modal('edit-user-modal')->show();
};

$updateUser = function () {
    abort_if(auth()->user()->cannot('users.update'), 403);
    $user = User::findOrFail($this->editUserId);

    $rules = [
        'editUserName' => 'required|string|max:255',
        'editUserEmail' => 'required|string|email|max:255|unique:users,email,' . $user->id,
    ];

    if (!empty($this->editUserPassword)) {
        $rules['editUserPassword'] = 'required|string|min:8|same:editUserPasswordConfirmation';
    }

    $this->validate($rules);

    $user->name = $this->editUserName;
    $user->email = $this->editUserEmail;
    
    if (!empty($this->editUserPassword)) {
        $user->password = \Illuminate\Support\Facades\Hash::make($this->editUserPassword);
    }

    $user->save();

    \Flux::toast('Data pengguna berhasil diperbarui!', variant: 'success');
    \Flux::modal('edit-user-modal')->close();
};

// Listener for when roles are updated
on(['role-updated' => function () {
    // Refresh list if needed
}]);

?>

<x-pages::settings.layout :heading="__('Users & Roles')" :subheading="__('Manage system users and assign roles.')">
    
    <div class="mb-6 flex justify-between items-center">
        <div class="w-full sm:w-64">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari user..." />
        </div>
        <div>
            <div class="flex gap-2">
                @can('users.create')
                <flux:button x-on:click="$flux.modal('create-user-modal').show()" variant="ghost" icon="user-plus">Tambah User</flux:button>
                @endcan
                @role('Super Admin')
                <flux:button x-on:click="$flux.modal('roles-list-modal').show()" variant="primary" icon="shield-check">Kelola Jabatan</flux:button>
                @endrole
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden mb-6 shadow-sm px-4 pb-2">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nama & Email</flux:table.column>
                <flux:table.column>Role Aktif</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->getUsers() as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" :initials="$user->initials()" :src="$user->avatarUrl()" />
                                <div class="flex items-center gap-2">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</span>
                                        <span class="text-xs text-zinc-500">{{ $user->email }}</span>
                                    </div>
                                    @can('users.update')
                                        <flux:button size="sm" x-on:click="$wire.editUser({{ $user->id }})" variant="subtle" icon="pencil-square" class="text-zinc-400 hover:text-zinc-700 px-2 h-7" />
                                    @endcan
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @forelse($user->roles as $role)
                                    <flux:badge size="sm" color="{{ $role->name === 'Super Admin' ? 'red' : 'blue' }}">
                                        {{ $role->name }}
                                    </flux:badge>
                                @empty
                                    <span class="text-xs text-zinc-400 italic">No Role</span>
                                @endforelse
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($user->id !== auth()->id())
                                @can('users.update')
                                <flux:button size="sm" x-on:click="$dispatch('open-assign-role', { userId: {{ $user->id }} })" variant="ghost" class="text-blue-600 hover:text-blue-700">Ubah Role</flux:button>
                                @endcan
                            @else
                                <span class="text-xs text-zinc-400 italic">Akun Anda</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            <div class="flex flex-col items-center justify-center py-8 text-zinc-500">
                                <flux:icon.users class="w-12 h-12 mb-3 text-zinc-300" />
                                <p>Tidak ada pengguna ditemukan.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if($this->getUsers()->hasMorePages())
        <div class="flex justify-center mt-4">
            <flux:button wire:click="loadMore" variant="subtle" class="w-full sm:w-auto">Muat Lebih Banyak</flux:button>
        </div>
    @endif

    {{-- Modal Tambah User --}}
    <flux:modal name="create-user-modal" class="md:w-96">
        <form wire:submit="createUser" class="space-y-6">
            <div>
                <flux:heading size="lg">Tambah Pengguna Baru</flux:heading>
                <flux:subheading>
                    Daftarkan akun karyawan baru ke dalam sistem.
                </flux:subheading>
            </div>

            <flux:input wire:model="newUserName" label="Nama Lengkap" required />
            <flux:input wire:model="newUserEmail" type="email" label="Alamat Email" required />
            <flux:input wire:model="newUserPassword" type="password" label="Kata Sandi" required viewable />
            <flux:input wire:model="newUserPasswordConfirmation" type="password" label="Konfirmasi Kata Sandi" required viewable />

            <div class="flex mt-6 gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost"> Batal </flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Daftarkan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Edit User --}}
    <flux:modal name="edit-user-modal" class="md:w-96">
        <form wire:submit="updateUser" class="space-y-6">
            <div>
                <flux:heading size="lg">Edit Pengguna</flux:heading>
                <flux:subheading>
                    Ubah informasi dasar pengguna.
                </flux:subheading>
            </div>

            <flux:input wire:model="editUserName" label="Nama Lengkap" required />
            <flux:input wire:model="editUserEmail" type="email" label="Alamat Email" required />
            
            <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <flux:label class="mb-2">Ubah Kata Sandi <span class="text-zinc-400 text-xs font-normal">(Kosongkan jika tidak ingin diubah)</span></flux:label>
                <div class="space-y-4 mt-2">
                    <flux:input wire:model="editUserPassword" type="password" placeholder="Kata sandi baru" viewable />
                    <flux:input wire:model="editUserPasswordConfirmation" type="password" placeholder="Konfirmasi kata sandi baru" viewable />
                </div>
            </div>

            <div class="flex mt-6 gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost"> Batal </flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan Perubahan</flux:button>
            </div>
        </form>
    </flux:modal>

    <livewire:settings.assign-role-modal />

    {{-- Modal List Jabatan --}}
    <flux:modal name="roles-list-modal" class="md:w-[800px]">
        <livewire:settings.roles />
    </flux:modal>

</x-pages::settings.layout>
