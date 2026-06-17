<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProductionRecipeFactory;

class ProductionRecipe extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(ProductionRecipeItem::class);
    }

    public function item()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Item::class);
    }

    // protected static function newFactory(): ProductionRecipeFactory
    // {
    //     // return ProductionRecipeFactory::new();
    // }
}
