<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Purchase\Database\Factories\PurchaseOrderFactory;

class PurchaseOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // protected static function newFactory(): PurchaseOrderFactory
    // {
    //     // return PurchaseOrderFactory::new();
    // }
}
