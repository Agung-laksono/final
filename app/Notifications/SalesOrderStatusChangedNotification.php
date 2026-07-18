<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SalesOrderStatusChangedNotification extends Notification
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
        $status = $this->salesOrder->status;
        $title = $status === 'processing' ? 'Sales Order Disetujui' : 'Sales Order Ditolak';
        $message = "Sales Order <b>" . e($this->salesOrder->so_number) . "</b> telah " . ($status === 'processing' ? 'disetujui' : 'ditolak') . " oleh " . e($this->actor->name) . ".";
        $icon = $status === 'processing' ? 'check-circle' : 'x-circle';
        $color = $status === 'processing' ? 'text-green-500' : 'text-red-500';

        return [
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'color' => $color,
            'url' => route('sales.orders.index') . '?show_detail=' . $this->salesOrder->id,
        ];
    }
}
