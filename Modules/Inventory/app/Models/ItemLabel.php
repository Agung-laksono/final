<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemLabel extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Label fisik ini adalah untuk barang (Item) apa?
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Saat ini fisik barang bernomor seri tersebut sedang berada di Gudang mana?
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
