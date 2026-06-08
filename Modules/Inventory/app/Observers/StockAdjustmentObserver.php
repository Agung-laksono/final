<?php

namespace Modules\Inventory\Observers;

use Modules\Inventory\Models\StockAdjustment;
use Modules\Inventory\Models\StockMovement;

class StockAdjustmentObserver
{
    /**
     * Handle the StockAdjustment "created" event.
     */
    public function created(StockAdjustment $adjustment): void
    {
        // Otomatis buat history pergerakan stok ketika Adjustment baru disimpan
        StockMovement::create([
            'item_id' => $adjustment->item_id,
            'warehouse_id' => $adjustment->warehouse_id,
            'type' => 'adjustment',
            'quantity' => $adjustment->difference,
            'reference_number' => $adjustment->reference_number,
            'date' => $adjustment->adjustment_date,
            'user_id' => $adjustment->user_id,
            'notes' => 'Stock opname adjustment: ' . $adjustment->reason,
        ]);
    }
}
