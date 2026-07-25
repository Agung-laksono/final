<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesReturn extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }
    
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    
    public function items()
    {
        return $this->hasMany(SalesReturnItem::class);
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
        $prefix = 'SR-';
        $latest = self::orderBy('id', 'desc')->first();
        if (!$latest) {
            return $prefix . '0001';
        }
        $number = intval(substr($latest->return_number, 3)) + 1;
        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
