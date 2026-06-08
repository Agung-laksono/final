<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockTransfer extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** Gudang Asal */
    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /** Gudang Tujuan */
    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /** Barang-barang yang ditransfer (Detail) */
    public function items()
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }
}
