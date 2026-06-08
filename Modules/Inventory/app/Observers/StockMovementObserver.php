<?php

namespace Modules\Inventory\Observers;

use Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockMovementObserver
{
    /**
     * Handle the StockMovement "creating" event.
     */
    public function creating(StockMovement $movement): void
    {
        // Dapatkan stok terakhir di gudang saat ini
        $currentStock = DB::table('item_warehouse')
            ->where('item_id', $movement->item_id)
            ->where('warehouse_id', $movement->warehouse_id)
            ->value('stock') ?? 0;

        $movement->stock_before = $currentStock;
        
        // Kita asumsikan 'quantity' dari pemanggil sudah bernilai + atau -
        // berdasarkan jenis transaksi.
        $movement->stock_after = $currentStock + $movement->quantity;
    }

    /**
     * Handle the StockMovement "created" event.
     */
    public function created(StockMovement $movement): void
    {
        // Pengecekan eksplisit agar kompatibel dengan SQLite dan timestamp
        $exists = DB::table('item_warehouse')
            ->where('warehouse_id', $movement->warehouse_id)
            ->where('item_id', $movement->item_id)
            ->exists();

        if ($exists) {
            DB::table('item_warehouse')
                ->where('warehouse_id', $movement->warehouse_id)
                ->where('item_id', $movement->item_id)
                ->update([
                    'stock' => $movement->stock_after,
                    'updated_at' => now()
                ]);
        } else {
            DB::table('item_warehouse')->insert([
                'warehouse_id' => $movement->warehouse_id,
                'item_id' => $movement->item_id,
                'stock' => $movement->stock_after,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
