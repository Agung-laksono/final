<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Bersihkan data lama (Karena kita transisi ke sistem BREAD)
        \DB::table('role_has_permissions')->delete();
        Permission::query()->delete();
        Role::query()->delete();

        // Buat Permissions Dasar (Hierarki 3-Bagian untuk Inventory)
        $permissions = [
            // Kunci Utama Modul
            'inventory.view',
            'inventory.create', // Master data dasar (opsional)
            'inventory.update',
            'inventory.delete',

            // Sub-Menu: Barang
            'inventory.item.view',
            'inventory.item.create',
            'inventory.item.update',
            'inventory.item.delete',

            // Sub-Menu: Gudang
            'inventory.warehouse.view',
            'inventory.warehouse.create',
            'inventory.warehouse.update',
            'inventory.warehouse.delete',

            // Sub-Menu: Transfer Barang
            'inventory.transfer.view',
            'inventory.transfer.create',
            'inventory.transfer.update',
            'inventory.transfer.delete',

            // Sub-Menu: Riwayat Mutasi
            'inventory.movement.view',

            // Sub-Menu: Opname
            'inventory.opname.view',
            'inventory.opname.create',
            'inventory.opname.update',
            'inventory.opname.delete',
            
            // Modul Sales
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.delete',

            // Modul Pegawai / Settings
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Buat Roles dan berikan permissions
        $roleSuperAdmin = Role::create(['name' => 'Super Admin']);
        // Super Admin mendapatkan semua permission via Gate::before di AppServiceProvider

        $roleManager = Role::create(['name' => 'Manager']);
        $roleManager->givePermissionTo(Permission::all()); // Manager dapat semua hak (tapi bisa disesuaikan nanti)

        $roleGudang = Role::create(['name' => 'Gudang']);
        $roleGudang->givePermissionTo([
            'inventory.view', // Kunci masuk
            'inventory.item.view', 'inventory.item.create', 'inventory.item.update',
            'inventory.warehouse.view',
            'inventory.transfer.view', 'inventory.transfer.create', 'inventory.transfer.update',
            'inventory.movement.view',
            'inventory.opname.view', 'inventory.opname.create', 'inventory.opname.update',
            // Gudang tidak punya hak delete apapun
        ]);

        $roleSales = Role::create(['name' => 'Sales']);
        $roleSales->givePermissionTo([
            'sales.view',
            'sales.create',
            'sales.update',
            // Sales hanya bisa lihat daftar barang dan stok
            'inventory.view',
            'inventory.item.view',
            'inventory.warehouse.view'
        ]);

        $roleMarketing = Role::create(['name' => 'Marketing']);
        $roleMarketing->givePermissionTo([
            'inventory.view', 
            'sales.view'
        ]);

        // Jadikan user pertama di sistem sebagai Super Admin
        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->assignRole('Super Admin');
        }
    }
}
