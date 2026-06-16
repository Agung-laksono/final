<?php

namespace Modules\Purchase\Database\Seeders;

use Illuminate\Database\Seeder;

class PurchaseDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendorFiles = \Illuminate\Support\Facades\Storage::disk('public')->files('vendors');
        
        $vendors = [
            ['name' => 'PT. Kayu Jati Abadi', 'type' => 'Supplier'],
            ['name' => 'CV. Makmur Sentosa', 'type' => 'Supplier'],
            ['name' => 'Toko Besi Maju', 'type' => 'Supplier'],
            ['name' => 'Kain Indah', 'type' => 'Supplier'],
            ['name' => 'Ekspedisi Kilat', 'type' => 'Ekspedisi'],
            ['name' => 'Pengrajin Rotan Budi', 'type' => 'Pengrajin'],
            ['name' => 'PT. Busa Nyaman', 'type' => 'Supplier'],
            ['name' => 'Kaca Bening', 'type' => 'Supplier'],
            ['name' => 'Cargo Cepat', 'type' => 'Ekspedisi'],
            ['name' => 'HPL Nusantara', 'type' => 'Supplier'],
        ];

        foreach ($vendors as $vendor) {
            $image = null;
            if (count($vendorFiles) > 0) {
                $image = $vendorFiles[array_rand($vendorFiles)];
            }

            \Modules\Purchase\Models\Vendor::create([
                'name' => $vendor['name'],
                'phone' => '0812' . rand(10000000, 99999999),
                'address' => 'Jl. Contoh Alamat No. ' . rand(1, 100),
                'province' => 'Jawa Tengah',
                'city' => 'Jepara',
                'district' => 'Tahunan',
                'village' => 'Senan',
                'image' => $image,
                'type' => $vendor['type'],
            ]);
        }
    }
}
