<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductionItemReceivedNotification extends Notification
{
    use Queueable;

    public $orderNumber;
    public $warehouseName;

    /**
     * Create a new notification instance.
     */
    public function __construct($orderNumber, $warehouseName)
    {
        $this->orderNumber = $orderNumber;
        $this->warehouseName = $warehouseName;
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
            'title' => 'Penerimaan Gudang Selesai',
            'message' => "Barang dari SPK {$this->orderNumber} telah di-QC dan diterima oleh Gudang {$this->warehouseName}.",
            'icon' => 'check-badge',
            'color' => 'text-emerald-500',
            'url' => route('production.orders'),
        ];
    }
}
