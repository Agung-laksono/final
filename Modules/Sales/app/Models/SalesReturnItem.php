<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesReturnItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class);
    }
    
    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrderItem::class);
    }
    
    public function item()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Item::class);
    }
}
