<?php

namespace App\Traits;

use App\Events\MenuBadgesUpdated;

trait UpdatesMenuBadges
{
    /**
     * Boot the trait to listen for Eloquent events.
     */
    public static function bootUpdatesMenuBadges(): void
    {
        static::saved(function ($model) {
            broadcast(new MenuBadgesUpdated())->toOthers();
        });

        static::deleted(function ($model) {
            broadcast(new MenuBadgesUpdated())->toOthers();
        });
    }
}
