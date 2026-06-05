<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemPriceHistory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Riwayat harga ini milik barang (Item) yang mana?
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
