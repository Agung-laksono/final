<?php

use function Livewire\Volt\{state, on, with};
use App\Models\User;
use Spatie\Permission\Models\Role;
use Livewire\Attributes\Validate;

state([
    'userId' => null,
    'user' => null,
    'selectedRoles' => [],
]);

$getAvailableRoles = function () {
    return Role::pluck('name')->toArray();
};

on(['open-assign-role' => function (int $userId) {
    $this->userId = $userId;
    $this->user = User::with('roles')->find($this->userId);
    
    if ($this->user) {
        $this->selectedRoles = $this->user->roles->pluck('name')->toArray();
        \Flux::modal('assign-role-modal')->show();
    }
}]);

$save = function () {
    if (!$this->user) return;
    
    // Mencegah menghapus akses Super Admin jika itu akun terakhir, dll
    // Tapi untuk kesederhanaan, kita langsung sync:
    $this->user->syncRoles($this->selectedRoles);
    
    \Flux::toast('Hak akses berhasil diperbarui!');
    
    \Flux::modal('assign-role-modal')->close();
    $this->dispatch('role-updated');
};

?>

<flux:modal name="assign-role-modal" class="md:w-96">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Ubah Hak Akses</flux:heading>
            <flux:subheading>
                Tentukan wewenang yang dimiliki oleh pegawai ini.
            </flux:subheading>
        </div>

        @if($user)
            <div class="flex items-center gap-3 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                <flux:avatar size="sm" :initials="$user->initials()" />
                <div class="flex flex-col">
                    <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $user->name }}</span>
                    <span class="text-xs text-zinc-500">{{ $user->email }}</span>
                </div>
            </div>

            <div class="space-y-4 pt-2">
                <flux:checkbox.group wire:model="selectedRoles" label="Roles">
                    @foreach($this->getAvailableRoles() as $role)
                        <flux:checkbox :value="$role" :label="$role" />
                    @endforeach
                </flux:checkbox.group>
            </div>
        @endif

        <div class="flex mt-6 gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">Batal</flux:button>
            </flux:modal.close>
            <flux:button wire:click="save" variant="primary">Simpan Perubahan</flux:button>
        </div>
    </div>
</flux:modal>
