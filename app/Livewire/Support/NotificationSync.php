<?php

namespace App\Livewire\Support;

use App\Events\NotificationsRead;

class NotificationSync
{
    /**
     * Broadcast to other tabs that notifications have been read.
     * Extracted to avoid Volt blade compiler issues with `new` keyword.
     */
    public static function syncToOthers(int $userId, int $unreadCount): void
    {
        broadcast(new NotificationsRead($userId, $unreadCount))->toOthers();
    }
}
