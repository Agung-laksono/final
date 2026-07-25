<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseReturn extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
    
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
    
    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
    
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
    
    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
    
    public static function generateReturnNumber()
    {
        $prefix = 'PR-';
        $latest = self::orderBy('id', 'desc')->first();
        if (!$latest) {
            return $prefix . '0001';
        }
        $number = intval(substr($latest->return_number, 3)) + 1;
        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
