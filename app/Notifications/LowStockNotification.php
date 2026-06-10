<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public $item;
    public $currentStock;

    /**
     * Create a new notification instance.
     */
    public function __construct($item, $currentStock)
    {
        $this->item = $item;
        $this->currentStock = $currentStock;
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
            'title' => 'Peringatan Stok Menipis',
            'message' => "Stok barang <b>" . e($this->item->name) . "</b> saat ini sisa " . $this->currentStock . " (Batas Min: " . $this->item->min_stock . ").",
            'icon' => 'exclamation-triangle',
            'color' => 'text-amber-500',
            'url' => route('inventory.items') . '?show_item=' . $this->item->id,
        ];
    }
}
