<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** Dokumen Transfer Induk */
    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    /** Barang yang ditransfer */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
