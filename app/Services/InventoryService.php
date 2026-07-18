<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Events\InventoryUpdated;

class InventoryService
{
    /**
     * Menyesuaikan stok untuk item tertentu di suatu gudang dan mencatat riwayat pergerakannya (stock_movements).
     * Semua operasi dibungkus dalam Database Transaction agar aman (Atomic).
     */
    public function adjustStock(
        int $itemId,
        int $warehouseId,
        float $quantity,
        string $type, // 'in' or 'out'
        string $referenceNumber = null,
        string $notes = null
    ) {
        return DB::transaction(function () use ($itemId, $warehouseId, $quantity, $type, $referenceNumber, $notes) {
            $existingStock = DB::table('item_warehouse')
                ->where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $currentStock = $existingStock ? $existingStock->stock : 0;
            $newStock = $type === 'in' ? $currentStock + $quantity : $currentStock - $quantity;

            if ($existingStock) {
                DB::table('item_warehouse')
                    ->where('item_id', $itemId)
                    ->where('warehouse_id', $warehouseId)
                    ->update(['stock' => $newStock, 'updated_at' => now()]);
            } else {
                DB::table('item_warehouse')->insert([
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'stock' => $newStock,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::table('stock_movements')->insert([
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $currentStock,
                'stock_after' => $newStock,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'date' => now()->format('Y-m-d'),
                'user_id' => auth()->id() ?? 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            InventoryUpdated::safeDispatch("Stock {$type}: {$quantity} for Item #{$itemId}");

            return $newStock;
        });
    }

    /**
     * Memindahkan stok dari satu gudang ke gudang lain (Mutasi Gudang).
     */
    public function moveStock(
        int $itemId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $quantity,
        string $referenceNumber = null,
        string $notes = null
    ) {
        return DB::transaction(function () use ($itemId, $fromWarehouseId, $toWarehouseId, $quantity, $referenceNumber, $notes) {
            // Kurangi stok dari gudang asal
            $this->adjustStock($itemId, $fromWarehouseId, $quantity, 'out', $referenceNumber, $notes . ' (Mutasi Keluar)');
            // Tambahkan stok ke gudang tujuan
            $this->adjustStock($itemId, $toWarehouseId, $quantity, 'in', $referenceNumber, $notes . ' (Mutasi Masuk)');
        });
    }

    /**
     * Memvalidasi apakah pesanan produksi memiliki bahan baku yang cukup di gudang berdasarkan resep (BOM).
     * Mengembalikan array berisi status (true/false) dan daftar item yang kurang (deficit).
     */
    public function validateMaterialAvailability($order)
    {
        $recipe = DB::table('production_recipes')
            ->where('item_id', $order->item_id)
            ->where('is_active', true)
            ->first();
        
        if (!$recipe) {
            return ['status' => true, 'deficit' => []];
        }

        $recipeItems = DB::table('production_recipe_items')
            ->join('items', 'production_recipe_items.item_id', '=', 'items.id')
            ->where('production_recipe_id', $recipe->id)
            ->select('production_recipe_items.*', 'items.name')
            ->get();

        $deficitItems = [];

        foreach ($recipeItems as $ri) {
            $needed = $ri->qty * $order->requested_qty;
            
            $alreadyConsumed = DB::table('stock_movements')
                ->where('reference_number', $order->order_number)
                ->where('item_id', $ri->item_id)
                ->where('type', 'out')
                ->sum('quantity') ?? 0;
                
            $remainingNeeded = max(0, $needed - $alreadyConsumed);
                
            $stock = DB::table('item_warehouse')
                ->where('item_id', $ri->item_id)
                ->sum('stock') ?? 0;

            if ($stock < $remainingNeeded) {
                $deficitItems[] = "{$ri->name} (Butuh: {$remainingNeeded}, Fisik: {$stock})";
            }
        }

        return [
            'status' => count($deficitItems) === 0,
            'deficit' => $deficitItems
        ];
    }
}
