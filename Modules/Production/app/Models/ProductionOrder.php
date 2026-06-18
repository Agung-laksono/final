<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProductionOrderFactory;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function item()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Item::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(\Modules\Purchase\Models\PurchaseOrder::class, 'purchase_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function histories()
    {
        return $this->hasMany(ProductionOrderHistory::class);
    }

    // protected static function newFactory(): ProductionOrderFactory
    // {
    //     // return ProductionOrderFactory::new();
    // }
}
