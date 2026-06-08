<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ItemAddedNotification extends Notification
{
    use Queueable;

    public $item;
    public $creator;

    /**
     * Create a new notification instance.
     */
    public function __construct($item, $creator)
    {
        $this->item = $item;
        $this->creator = $creator;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Barang Baru Ditambahkan',
            'message' => "Barang '{$this->item->name}' baru saja ditambahkan oleh {$this->creator->name}.",
            'icon' => 'cube',
            'color' => 'text-green-500',
            'url' => route('inventory'),
        ];
    }
}
