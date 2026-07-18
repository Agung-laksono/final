<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Purchase\Models\PurchaseQueue;

class PurchaseQueueCreatedNotification extends Notification
{
    use Queueable;

    public PurchaseQueue $purchaseQueue;
    public string $source;

    /**
     * Create a new notification instance.
     */
    public function __construct(PurchaseQueue $purchaseQueue)
    {
        $this->purchaseQueue = $purchaseQueue;
        $this->source = match($purchaseQueue->source_type) {
            'low_stock' => 'Stok Menipis',
            'sales'     => 'Pesanan Penjualan',
            'manual'    => 'Manual',
            default     => 'Sistem',
        };
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
        $itemName = $this->purchaseQueue->item->name ?? 'Barang Tidak Diketahui';
        $qty      = $this->purchaseQueue->approved_qty ?? $this->purchaseQueue->requested_qty;
        $unit     = $this->purchaseQueue->item->unit->name ?? 'unit';

        return [
            'title'   => 'Permintaan Pembelian Baru',
            'message' => "Permintaan <b>" . e($itemName) . "</b> sebanyak <b>{$qty} {$unit}</b> dari <b>{$this->source}</b> masuk ke antrean pembelian.",
            'icon'    => 'shopping-cart',
            'color'   => 'text-sky-500',
            'url'     => route('purchase.queues.kanban'),
        ];
    }
}
