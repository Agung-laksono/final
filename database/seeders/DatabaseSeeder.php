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

        User::firstOrCreate(
            ['email' => 'a@a.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('semangat'), // berikan password default agar bisa login jika belum ada
            ]
        );

        $this->call([
            \Modules\Inventory\Database\Seeders\InventoryDatabaseSeeder::class,
        ]);
    }
}
