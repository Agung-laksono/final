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

        // Buat Permissions Dasar
        $permissions = [
            'view inventory',
            'manage inventory',
            'manage users',
            'view sales',
            'manage sales',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Buat Roles dan berikan permissions
        $roleSuperAdmin = Role::create(['name' => 'Super Admin']);
        // Super Admin mendapatkan semua permission via Gate::before di AuthServiceProvider / AppServiceProvider

        $roleManager = Role::create(['name' => 'Manager']);
        $roleManager->givePermissionTo(['view inventory', 'manage inventory', 'view sales', 'manage sales', 'manage users']);

        $roleGudang = Role::create(['name' => 'Gudang']);
        $roleGudang->givePermissionTo(['view inventory', 'manage inventory']);

        $roleSales = Role::create(['name' => 'Sales']);
        $roleSales->givePermissionTo(['view inventory', 'view sales', 'manage sales']);

        $roleMarketing = Role::create(['name' => 'Marketing']);
        $roleMarketing->givePermissionTo(['view inventory', 'view sales']);

        // Jadikan user pertama di sistem sebagai Super Admin
        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->assignRole('Super Admin');
        }
    }
}
