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
        return [
            'title' => 'Status Barang Berubah',
            'message' => "Status barang <b>" . e($this->item->name) . "</b> telah diubah menjadi " . ($this->item->is_active ? 'Aktif' : 'Non-aktif') . " oleh " . e($this->actor->name) . ".",
            'icon' => $this->item->is_active ? 'check-circle' : 'x-circle',
            'color' => $this->item->is_active ? 'text-green-500' : 'text-red-500',
            'url' => route('inventory.items') . '?show_item=' . $this->item->id,
        ];
    }
}
