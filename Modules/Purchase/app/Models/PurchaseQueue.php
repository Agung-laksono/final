<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Purchase\Database\Factories\PurchaseQueueFactory;

class PurchaseQueue extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    public function item()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Item::class);
    }

    public function fulfillments()
    {
        return $this->hasMany(PurchaseQueueFulfillment::class);
    }

    // protected static function newFactory(): PurchaseQueueFactory
    // {
    //     // return PurchaseQueueFactory::new();
    // }
}
