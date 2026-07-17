<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesOrder extends Model
{
    use HasFactory, \App\Traits\UpdatesMenuBadges;

    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function fulfillments()
    {
        return $this->hasMany(SalesOrderFulfillment::class);
    }

    public function payments()
    {
        return $this->hasMany(SalesPayment::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function brand()
    {
        return $this->belongsTo(\App\Models\Brand::class);
    }

    public function courierVendor()
    {
        return $this->belongsTo(\Modules\Purchase\Models\Vendor::class, 'courier_vendor_id');
    }
}
