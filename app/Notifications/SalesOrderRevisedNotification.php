<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SalesOrderRevisedNotification extends Notification
{
    use Queueable;

    public $salesOrder;
    public $actor;

    /**
     * Create a new notification instance.
     */
    public function __construct($salesOrder, $actor)
    {
        $this->salesOrder = $salesOrder;
        $this->actor = $actor;
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
            'title' => 'Sales Order Direvisi',
            'message' => "Sales Order <b>" . e($this->salesOrder->so_number) . "</b> telah direvisi/disesuaikan oleh " . e($this->actor->name) . ".",
            'icon' => 'pencil-square',
            'color' => 'text-amber-500',
            'url' => route('sales.orders.index') . '?show_detail=' . $this->salesOrder->id,
        ];
    }
}
