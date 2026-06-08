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
        // User::factory(10)->create();

        // 1. Buat User utama (jika belum ada)
        $user = User::firstOrCreate(
            ['email' => 'a@a.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('semangat'), // berikan password default agar bisa login jika belum ada
            ]
        );

        // 2. Panggil Seeder Role & Permissions (ini akan otomatis membuat Role dan memberikan Super Admin ke user pertama)
        $this->call([
            RolePermissionSeeder::class,
        ]);

        // 3. Jalankan seeder modul inventaris
        $this->call([
            \Modules\Inventory\Database\Seeders\InventoryDatabaseSeeder::class,
        ]);
    }
}
