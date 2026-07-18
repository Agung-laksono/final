<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SalesOrderWaitingApprovalNotification extends Notification
{
    use Queueable;

    public $salesOrder;
    public $creator;

    /**
     * Create a new notification instance.
     */
    public function __construct($salesOrder, $creator)
    {
        $this->salesOrder = $salesOrder;
        $this->creator = $creator;
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
        return [
            'title' => 'Persetujuan Sales Order',
            'message' => "Sales Order baru <b>" . e($this->salesOrder->so_number) . "</b> diajukan oleh " . e($this->creator->name) . " dan menunggu persetujuan.",
            'icon' => 'calculator',
            'color' => 'text-amber-500',
            'url' => route('sales.orders.index') . '?show_approval=' . $this->salesOrder->id,
        ];
    }
}
