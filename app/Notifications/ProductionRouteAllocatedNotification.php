<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductionRouteAllocatedNotification extends Notification
{
    use Queueable;

    public $orderNumber;
    public $vendorName;
    public $warehouseName;

    /**
     * Create a new notification instance.
     */
    public function __construct($orderNumber, $vendorName, $warehouseName)
    {
        $this->orderNumber = $orderNumber;
        $this->vendorName = $vendorName;
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
            'title' => 'Alokasi Pengiriman Baru',
            'message' => "Ada barang baru ({$this->orderNumber}) dari {$this->vendorName} menuju {$this->warehouseName}. Menunggu proses QC & Penerimaan.",
            'icon' => 'truck',
            'color' => 'text-blue-500',
            'url' => route('inventory.production-receipts'),
        ];
    }
}
