<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NormalMovementNotification extends Notification
{
    use Queueable;

    public $item;
    public $quantity;
    public $type; // 'Masuk' or 'Keluar'
    public $userName;
    public $userAvatar;

    /**
     * Create a new notification instance.
     */
    public function __construct($item, $quantity, $type, $userName = 'Sistem', $userAvatar = null)
    {
        $this->item = $item;
        $this->quantity = $quantity;
        $this->type = $type;
        $this->userName = $userName;
        $this->userAvatar = $userAvatar;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $color = $this->type === 'Masuk' ? 'text-green-500' : 'text-orange-500';
        $icon = $this->type === 'Masuk' ? 'arrow-down-tray' : 'arrow-up-tray';

        return [
            'title' => 'Mutasi Stok ' . $this->type,
            'message' => "Oleh <b>" . e($this->userName) . "</b>: Terjadi mutasi <b>" . $this->type . "</b> sebanyak " . number_format($this->quantity, 0, ',', '.') . " pcs untuk barang <b>" . e($this->item->name) . "</b>.",
            'avatar' => $this->userAvatar,
            'icon' => $icon,
            'color' => $color,
            'url' => route('inventory.movements') . '?search=' . e($this->item->code),
        ];
    }
}
