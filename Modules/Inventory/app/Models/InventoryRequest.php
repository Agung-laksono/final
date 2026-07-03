<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class InventoryRequest extends Model
{
    protected $guarded = ['id'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function routedBy()
    {
        return $this->belongsTo(User::class, 'routed_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function productionOrder()
    {
        return $this->hasOne(\Modules\Production\Models\ProductionOrder::class, 'reference_number', 'reference_number');
    }

    public function purchaseQueue()
    {
        return $this->hasOne(\Modules\Purchase\Models\PurchaseQueue::class, 'source_id')->where('source_type', 'inventory_request');
    }
}
