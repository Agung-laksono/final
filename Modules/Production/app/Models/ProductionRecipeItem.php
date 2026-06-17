<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProductionRecipeItemFactory;

class ProductionRecipeItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function item()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Item::class);
    }

    // protected static function newFactory(): ProductionRecipeItemFactory
    // {
    //     // return ProductionRecipeItemFactory::new();
    // }
}
