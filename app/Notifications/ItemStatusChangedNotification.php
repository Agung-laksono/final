<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ItemStatusChangedNotification extends Notification
{
    use Queueable;

    public $item;
    public $actor;

    /**
     * Create a new notification instance.
     */
    public function __construct($item, $actor)
    {
        $this->item = $item;
        $this->actor = $actor;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $status = $this->item->is_active ? 'diaktifkan' : 'dinonaktifkan';
        $color = $this->item->is_active ? 'text-blue-500' : 'text-red-500';
        return [
            'title' => 'Status Barang Diubah',
            'message' => "Barang <b>" . e($this->item->name) . "</b> telah $status oleh " . e($this->actor->name) . ".",
            'icon' => 'arrow-path',
            'color' => $color,
            'url' => route('inventory') . '?show_item=' . $this->item->id,
        ];
    }
}
