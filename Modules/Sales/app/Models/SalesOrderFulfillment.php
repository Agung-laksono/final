<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesOrderFulfillment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function item()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Item::class);
    }

    public function itemLabel()
    {
        return $this->belongsTo(\Modules\Inventory\Models\ItemLabel::class);
    }

    public function scanner()
    {
        return $this->belongsTo(\App\Models\User::class, 'scanned_by');
    }
}
