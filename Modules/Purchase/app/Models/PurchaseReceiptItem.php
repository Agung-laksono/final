<?php

namespace Modules\Purchase\Models;

use Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseReceiptItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
