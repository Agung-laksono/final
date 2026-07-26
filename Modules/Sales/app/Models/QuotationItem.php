<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Sales\Database\Factories\QuotationItemFactory;

class QuotationItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function item()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Item::class);
    }

    protected static function newFactory(): QuotationItemFactory
    {
        //return QuotationItemFactory::new();
    }
}
