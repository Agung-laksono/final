<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Purchase\Models\PurchaseOrder;

class PurchaseOrderWaitingApprovalNotification extends Notification
{
    use Queueable;

    public PurchaseOrder $order;
    public string $creatorName;

    /**
     * Create a new notification instance.
     */
    public function __construct(PurchaseOrder $order)
    {
        $this->order = $order;
        $this->creatorName = $order->creator ? explode(' ', $order->creator->name)[0] : 'Sistem';
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
            ? route('finance.payables') 
            : route('purchase.orders.kanban');

        return [
            'title'   => "Persetujuan {$type} Baru",
            'message' => "<b>{$this->creatorName}</b> membuat {$type} <b>{$this->order->po_number}</b> senilai <b>Rp " . number_format($this->order->total_amount, 0, ',', '.') . "</b> yang menunggu persetujuan (Approval) Anda.",
            'icon'    => 'document-check',
            'color'   => 'text-amber-500',
            'url'     => $url,
        ];
    }
}
