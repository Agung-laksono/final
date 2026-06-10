<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WarehouseTransferNotification extends Notification
{
    use Queueable;

    public $referenceNumber;
    public $messageStr;

    /**
     * Create a new notification instance.
     */
    public function __construct($referenceNumber, $messageStr)
    {
        $this->referenceNumber = $referenceNumber;
        $this->messageStr = $messageStr;
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
            'title' => 'Transfer Antar Gudang',
            'message' => $this->messageStr,
            'icon' => 'truck',
            'color' => 'text-blue-500',
            'url' => route('inventory.transfers'),
        ];
    }
}
