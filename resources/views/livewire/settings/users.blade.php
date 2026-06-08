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
    
    \Flux::toast('User baru berhasil didaftarkan!');
    \Flux::modal('create-user-modal')->close();
};

// Listener for when roles are updated
on(['role-updated' => function () {
    //
}]);

?>

<x-pages::settings.layout :heading="__('Users & Roles')" :subheading="__('Manage system users and assign roles.')">
    
    <div class="mb-6 flex justify-between items-center">
        <div class="w-full sm:w-64">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari user..." />
        </div>
        <div>
            <div class="flex gap-2">
                <flux:button x-on:click="$flux.modal('create-user-modal').show()" variant="ghost" icon="user-plus">Tambah User</flux:button>
                <flux:button x-on:click="$flux.modal('roles-list-modal').show()" variant="primary" icon="shield-check">Kelola Jabatan</flux:button>
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
                                <flux:avatar size="sm" :initials="$user->initials()" />
                                <div class="flex flex-col">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</span>
                                    <span class="text-xs text-zinc-500">{{ $user->email }}</span>
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
                                <flux:button size="sm" x-on:click="$dispatch('open-assign-role', { userId: {{ $user->id }} })" variant="ghost" class="text-blue-600 hover:text-blue-700">Ubah Role</flux:button>
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
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Daftarkan</flux:button>
            </div>
        </form>
    </flux:modal>

    <livewire:settings.assign-role-modal />

    {{-- Modal List Jabatan --}}
    <flux:modal name="roles-list-modal" class="md:w-[800px]">
        <livewire:settings.roles />
    </flux:modal>

</x-pages::settings.layout>
