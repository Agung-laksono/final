<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Purchase\Models\PurchaseOrder;

class PurchaseOrderStatusChangedNotification extends Notification
{
    use Queueable;

    public PurchaseOrder $order;
    public string $action; // 'disetujui' or 'ditolak'
    public string $actorName;

    /**
     * Create a new notification instance.
     */
    public function __construct(PurchaseOrder $order, string $action, string $actorName)
    {
        $this->order = $order;
        $this->action = $action;
        $this->actorName = $actorName;
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
        $isMaklon = str_starts_with($this->order->po_number, 'GUNJAS');
        $type = $isMaklon ? 'SPK Maklon' : 'Purchase Order';
        
        $url = $isMaklon 
            ? route('production.orders') 
            : route('purchase.orders.kanban');

        $color = $this->action === 'disetujui' ? 'text-emerald-500' : 'text-red-500';
        $icon = $this->action === 'disetujui' ? 'check-circle' : 'x-circle';

        return [
            'title'   => "{$type} {$this->action}",
            'message' => "{$type} <b>{$this->order->po_number}</b> telah <b>{$this->action}</b> oleh {$this->actorName}.",
            'icon'    => $icon,
            'color'   => $color,
            'url'     => $url,
        ];
    }
}
