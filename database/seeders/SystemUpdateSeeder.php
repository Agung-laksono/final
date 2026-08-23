<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SystemUpdateSeeder extends Seeder
{
    /**
     * Seeder Khusus Update Sistem Production:
     * HANYA mendaftarkan/memperbarui Role, Permission, Menu Navigasi, & Pengaturan Sistem.
     * SAMA SEKALI TIDAK membuat data dummy (barang, transaksi, dll).
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            NavigationItemSeeder::class,
            WorkflowSettingSeeder::class,
        ]);
    }
}
