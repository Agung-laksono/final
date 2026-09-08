<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogItem extends Model
{
    protected $guarded = ['id'];

    public function catalog()
    {
        return $this->belongsTo(Catalog::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
