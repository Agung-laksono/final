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
        $units = ['Pieces', 'Kilogram', 'Gram', 'Liter', 'Meter', 'Centimeter', 'Box', 'Lusin', 'Kodi', 'Pack', 'Roll'];
        foreach ($units as $unit) {
            Unit::firstOrCreate(['name' => $unit]);
        }

        // 2. Seed Types
        $types = [
            'Bahan Baku Utama', 
            'Bahan Baku Penolong', 
            'Produk Jadi', 
            'Barang Setengah Jadi',
            'ATK', 
            'Asset', 
            'Sparepart', 
            'Konsumsi',
            'Peralatan Kebersihan'
        ];
        foreach ($types as $type) {
            Type::firstOrCreate(['name' => $type]);
        }

        // 3. Seed Categories and SubCategories
        $categories = [
            'Ruang Makan' => ['Set Meja Kursi', 'Meja Makan', 'Kursi Makan', 'Lemari Dapur', 'Rak Piring'],
            'Ruang Tidur' => ['Ranjang', 'Lemari Pakaian', 'Meja Rias', 'Nakas', 'Kasur / Springbed'],
            'Ruang Tamu' => ['Set Tamu', 'Sofa', 'Meja Tamu', 'Bufet TV', 'Rak Sepatu'],
            'Outdoor' => ['Kursi Taman', 'Meja Teras', 'Ayunan', 'Lampu Taman'],
            'Ruang Kerja' => ['Meja Kerja', 'Kursi Kantor', 'Rak Buku', 'Laci Arsip'],
            'Dekorasi' => ['Lukisan', 'Jam Dinding', 'Lampu Gantung', 'Karpet', 'Vas Bunga'],
        ];

        foreach ($categories as $catName => $subCats) {
            $category = Category::firstOrCreate(['name' => $catName]);
            
            foreach ($subCats as $subCatName) {
                SubCategory::firstOrCreate([
                    'category_id' => $category->id,
                    'name' => $subCatName
                ]);
            }
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
