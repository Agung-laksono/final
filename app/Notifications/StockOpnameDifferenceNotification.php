<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StockOpnameDifferenceNotification extends Notification
{
    use Queueable;

    public $referenceNumber;

    /**
     * Create a new notification instance.
     */
    public function __construct($referenceNumber)
    {
        $this->referenceNumber = $referenceNumber;
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
            'title' => 'Selisih Stock Opname',
            'message' => "Terdapat selisih/penyesuaian pada hasil Stock Opname dengan No. Referensi <b>" . e($this->referenceNumber) . "</b>.",
            'icon' => 'document-magnifying-glass',
            'color' => 'text-orange-500',
            'url' => route('inventory.stock-opname'),
        ];
    }
}
