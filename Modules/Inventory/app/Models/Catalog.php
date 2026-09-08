<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class Catalog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'valid_until' => 'datetime',
    ];

    public function items()
    {
        return $this->belongsToMany(Item::class, 'catalog_items')
                    ->withPivot('quantity', 'sort_order')
                    ->withTimestamps();
    }

    public function catalogItems()
    {
        return $this->hasMany(CatalogItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Auto cleanup / prune expired catalogs that are older than 7 days
     */
    public static function pruneExpired()
    {
        static::where('valid_until', '<', now()->subDays(7))->delete();
    }
}
