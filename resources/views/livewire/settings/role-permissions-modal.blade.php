<?php

use function Livewire\Volt\{state, on};
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

state([
    'roleId' => null,
    'role' => null,
    'selectedPermissions' => [],
]);

$getAvailablePermissions = function () {
    return Permission::pluck('name')->toArray();
};

on(['open-role-permissions' => function (int $roleId) {
    $this->roleId = $roleId;
    $this->role = Role::with('permissions')->find($this->roleId);
    
    if ($this->role && $this->role->name !== 'Super Admin') {
        $this->selectedPermissions = $this->role->permissions->pluck('name')->toArray();
        \Flux::modal('role-permissions-modal')->show();
    }
}]);

$save = function () {
    if (!$this->role || $this->role->name === 'Super Admin') return;
    
    $this->role->syncPermissions($this->selectedPermissions);
    
    \Flux::toast("Wewenang untuk jabatan {$this->role->name} berhasil diperbarui!");
    \Flux::modal('role-permissions-modal')->close();
    
    // Memberitahu parent untuk refresh table
    $this->dispatch('permissions-updated');
};

?>

<div>
    <template x-teleport="body">
        <flux:modal name="role-permissions-modal" class="w-full" style="width: 800px; max-width: 90vw;" scroll="body" :dismissible="false">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Atur Wewenang Jabatan</flux:heading>
                    <flux:subheading>
                        Pilih aksi apa saja yang boleh dilakukan oleh jabatan ini.
                    </flux:subheading>
                </div>

                @if($role)
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                        <flux:icon.shield-check class="w-6 h-6 text-zinc-500" />
                        <div class="flex flex-col">
                            <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100">Jabatan: {{ $role->name }}</span>
                            <span class="text-xs text-zinc-500">Centang wewenang yang diizinkan</span>
                        </div>
                    </div>

                    <div class="pt-4 overflow-x-auto">
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden min-w-[600px]">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-xs uppercase tracking-wider text-zinc-500 font-bold border-b border-zinc-200 dark:border-zinc-700">
                                    <tr>
                                        <th class="px-4 py-3 whitespace-nowrap">Modul Fitur</th>
                                        <th class="px-4 py-3 text-center whitespace-nowrap">Lihat (View)</th>
                                        <th class="px-4 py-3 text-center whitespace-nowrap">Tambah (Create)</th>
                                        <th class="px-4 py-3 text-center whitespace-nowrap">Ubah (Update)</th>
                                        <th class="px-4 py-3 text-center whitespace-nowrap text-rose-500">Hapus (Delete)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @php
                                        $permissions = $this->getAvailablePermissions();
                                        $grouped = [];
                                        foreach ($permissions as $p) {
                                            $parts = explode('.', $p);
                                            if (count($parts) >= 2) {
                                                $action = array_pop($parts); // Ambil elemen terakhir (view, create, dll)
                                                $module = implode(' › ', $parts); // Gabung sisanya dengan separator
                                                $grouped[$module][$action] = $p;
                                            } else {
                                                $grouped['Lainnya'][$p] = $p;
                                            }
                                        }
                                    @endphp

                                    @foreach($grouped as $module => $actions)
                                        @if($module !== 'Lainnya')
                                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                            <td class="px-4 py-3 font-semibold capitalize text-zinc-900 dark:text-zinc-100 border-r border-zinc-200 dark:border-zinc-700/50 bg-zinc-50/30 dark:bg-zinc-800/10 whitespace-nowrap">
                                                {{ $module }}
                                            </td>
                                            @foreach(['view', 'create', 'update', 'delete'] as $action)
                                                <td class="px-4 py-3 text-center">
                                                    @if(isset($actions[$action]))
                                                        <div class="flex justify-center">
                                                            <flux:checkbox wire:model="selectedPermissions" value="{{ $actions[$action] }}" />
                                                        </div>
                                                    @else
                                                        <span class="text-zinc-300 dark:text-zinc-600 font-mono">-</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
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
    </template>
</div>
