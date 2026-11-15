<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Purchase\Database\Factories\PurchaseOrderFactory;

class PurchaseOrder extends Model
{
    use HasFactory, \App\Traits\UpdatesMenuBadges, \App\Traits\SearchableAiKnowledge;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts()
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function payments()
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function mergedInto()
    {
        return $this->belongsTo(PurchaseOrder::class, 'merged_into_po_id');
    }

    public function mergedOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'merged_into_po_id');
    }

    public function recalculateTotal(): void
    {
        $itemsSubtotal = (float) $this->items()->sum('subtotal');
        $ongkir = (float) ($this->ongkir ?? $this->shipping_fee ?? 0);
        $diskon = (float) ($this->diskon_global ?? $this->discount ?? 0);
        $pajak = (float) ($this->pajak ?? $this->tax ?? 0);

        $this->total_amount = max(0, $itemsSubtotal + $ongkir - $diskon + $pajak);
        $this->save();
    }

    // protected static function newFactory(): PurchaseOrderFactory
    // {
    //     // return PurchaseOrderFactory::new();
    // }
}
