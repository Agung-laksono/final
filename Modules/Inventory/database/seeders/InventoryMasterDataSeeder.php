<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Type;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\SubCategory;

class InventoryMasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Units
        $units = ['pcs', 'meter', 'roll', 'gulung', 'kg'];
        foreach ($units as $unit) {
            Unit::firstOrCreate(['name' => $unit]);
        }

        // 2. Seed Types
        $types = [
            'produk jadi', 
            'bahan baku utama', 
            'bahan baku penolong', 
            'atk', 
            'asset', 
            'lainnya'
        ];
        foreach ($types as $type) {
            Type::firstOrCreate(['name' => $type]);
        }

        // 3. Seed Categories (without subcategories)
        $categories = [
            'Ruang kerja', 
            'ruang makan', 
            'ruang tamu', 
            'ruang tidur', 
            'outdoor'
        ];

        foreach ($categories as $catName) {
            Category::firstOrCreate(['name' => $catName]);
        }

        // 4. Seed Warehouses
        $warehouses = [
            ['code' => 'G-PNK', 'name' => 'Gudang Pink', 'address' => 'Gedung A'],
            ['code' => 'G-HJU', 'name' => 'Gudang Hijau', 'address' => 'Gedung B'],
            ['code' => 'G-BRU', 'name' => 'Gudang Biru', 'address' => 'Gedung C'],
        ];

        foreach ($warehouses as $wh) {
            \Modules\Inventory\Models\Warehouse::firstOrCreate(['code' => $wh['code']], $wh);
        }
    }
}
