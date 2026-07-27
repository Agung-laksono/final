<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class NavigationItem extends Model
{
    protected $fillable = [
        'route_name', 'label', 'icon_type', 'icon', 'image_path', 'section', 'sub_group', 'badge_type', 'menu_column', 'sort_order', 'permission', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function clearCache(): void
    {
        Cache::forget('navigation_items_grouped');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }
}
