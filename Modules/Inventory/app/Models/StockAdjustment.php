<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** Gudang tempat stok opname dilakukan */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Barang yang disesuaikan stoknya */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
