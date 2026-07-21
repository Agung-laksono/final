<?php

namespace App\Services;

use App\Models\NavigationItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class NavigationService
{
    /**
     * Ambil semua item navigasi aktif, dikelompokkan per section.
     * Hasilnya di-cache untuk performa.
     */
    public static function getGrouped(): array
    {
        return Cache::remember('navigation_items_grouped', 60 * 60, function () {
            return NavigationItem::active()
                ->orderBy('sort_order')
                ->get()
                ->groupBy('section')
                ->toArray();
        });
    }

}
