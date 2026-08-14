<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Buat User utama (Super Admin)
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@a.com'],
            [
                'name' => 'admin (Super Admin)',
                'password' => bcrypt('semangat'),
            ]
        );

        // 2. Panggil Seeder Role & Permissions & Settings
        // (Ini akan membuat Roles, Permissions, Settings, dan assign Super Admin ke user pertama)
        $this->call([
            RolePermissionSeeder::class,
            NavigationItemSeeder::class,
            WorkflowSettingSeeder::class,
        ]);

        // 3. Buat User untuk masing-masing Divisi / Role
        $usersData = [
            ['email' => 'manager@a.com', 'name' => 'Manager User', 'role' => 'Manager'],
            ['email' => 'gudang@a.com', 'name' => 'Gudang User', 'role' => 'Kepala Gudang'],
            ['email' => 'purchasing@a.com', 'name' => 'Purchasing User', 'role' => 'Kepala Purchasing'],
            ['email' => 'sales@a.com', 'name' => 'Sales User', 'role' => 'Staf Sales'],
            ['email' => 'kepalasales@a.com', 'name' => 'Kepala Sales', 'role' => 'Kepala Sales'],
            ['email' => 'finance@a.com', 'name' => 'Finance User', 'role' => 'Kepala Finance'],
            ['email' => 'marketing@a.com', 'name' => 'Marketing User', 'role' => 'Staf Marketing'],
            ['email' => 'stafgudang@a.com', 'name' => 'Staf Gudang', 'role' => 'Staf Gudang'],
        ];

        foreach ($usersData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('semangat'),
                ]
            );
            $user->assignRole($data['role']);
        }

        // 4. Jalankan seeder modul inventaris dan purchase
        $this->call([
            \Modules\Inventory\Database\Seeders\InventoryDatabaseSeeder::class,
            \Modules\Purchase\Database\Seeders\PurchaseDatabaseSeeder::class,
        ]);
    }
}
