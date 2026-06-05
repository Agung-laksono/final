<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockMovement extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Mutasi stok ini adalah pergerakan untuk barang (Item) apa?
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Mutasi ini terjadi di Gudang (Warehouse) mana?
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
