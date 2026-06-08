<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;

class InventoryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Memanggil Master Data Seeder (Kategori, Satuan, Gudang dll)
        $this->call([
            InventoryMasterDataSeeder::class,
        ]);

        // Menjalankan Item Factory untuk membuat 50 data dummy
        $items = \Modules\Inventory\Models\Item::factory(50)->create();

        // Mendapatkan semua gudang
        $warehouses = \Modules\Inventory\Models\Warehouse::all();

        if ($warehouses->count() > 0) {
            foreach ($items as $item) {
                // Berikan stok secara acak ke 1-2 gudang
                $randomWarehouses = $warehouses->random(rand(1, 2));
                
                $counter = 1;
                $labelsToInsert = [];
                
                foreach ($randomWarehouses as $wh) {
                    $qty = rand(5, 20); // Kuantitas fisik per gudang
                    
                    $item->warehouses()->attach($wh->id, [
                        'stock' => $qty,
                    ]);
                    
                    // Generate label fisik HANYA untuk barang yang memerlukan label (Serial Number)
                    // Barang biasa (requires_label = false) cukup pakai angka stok di pivot saja
                    if ($item->requires_label) {
                        for ($i = 0; $i < $qty; $i++) {
                            $labelsToInsert[] = [
                                'item_id'      => $item->id,
                                'warehouse_id' => $wh->id,
                                'label_code'   => $item->code . '-' . date('ym') . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT),
                                'status'       => 'in_stock',
                                'created_at'   => now(),
                                'updated_at'   => now(),
                            ];
                            $counter++;
                        }
                    }
                }
                
                // Bulk insert untuk performa yang lebih cepat
                if (count($labelsToInsert) > 0) {
                    \Modules\Inventory\Models\ItemLabel::insert($labelsToInsert);
                }
            }
        }
    }
}
