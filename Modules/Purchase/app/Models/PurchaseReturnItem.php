<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseReturnItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }
    
    public function purchaseReceiptItem()
    {
        return $this->belongsTo(PurchaseReceiptItem::class);
    }
    
    public function item()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Item::class);
    }
}
