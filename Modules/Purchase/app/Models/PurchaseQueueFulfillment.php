<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Purchase\Database\Factories\PurchaseQueueFulfillmentFactory;

class PurchaseQueueFulfillment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    public function purchaseQueue()
    {
        return $this->belongsTo(PurchaseQueue::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    // protected static function newFactory(): PurchaseQueueFulfillmentFactory
    // {
    //     // return PurchaseQueueFulfillmentFactory::new();
    // }
}
