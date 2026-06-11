<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Purchase\Database\Factories\PurchaseOrderItemFactory;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Item::class);
    }

    public function receipts()
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    // protected static function newFactory(): PurchaseOrderItemFactory
    // {
    //     // return PurchaseOrderItemFactory::new();
    // }
}
