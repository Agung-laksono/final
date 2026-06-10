<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AbnormalMovementNotification extends Notification
{
    use Queueable;

    public $item;
    public $quantity;
    public $type; // 'Masuk' or 'Keluar'

    /**
     * Create a new notification instance.
     */
    public function __construct($item, $quantity, $type)
    {
        $this->item = $item;
        $this->quantity = $quantity;
        $this->type = $type;
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
        return [
            'title' => 'Pergerakan Stok Tidak Wajar',
            'message' => "Terdeteksi mutasi <b>" . $this->type . "</b> sebanyak " . number_format($this->quantity, 0, ',', '.') . " pcs untuk barang <b>" . e($this->item->name) . "</b>.",
            'icon' => 'shield-exclamation',
            'color' => 'text-red-500',
            'url' => route('inventory.movements') . '?search=' . e($this->item->code),
        ];
    }
}
