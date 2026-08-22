<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Sales\Database\Factories\QuotationFactory;

class Quotation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(\Modules\Sales\Models\Customer::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'converted_to_so_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->quotation_number)) {
                $model->quotation_number = \App\Services\CodeGenerator::generateNextCode(static::class, 'quotation_number', 'SQ-');
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    protected static function newFactory(): QuotationFactory
    {
        //return QuotationFactory::new();
    }
}
