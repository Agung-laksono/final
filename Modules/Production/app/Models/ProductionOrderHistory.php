<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderHistory extends Model
{
    protected $fillable = [
        'production_order_id',
        'phase',
        'purchase_order_id',
        'vendor_id',
        'status',
        'notes',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(\Modules\Purchase\Models\PurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\Modules\Purchase\Models\Vendor::class);
    }
}
