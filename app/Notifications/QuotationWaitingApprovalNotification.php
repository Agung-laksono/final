<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuotationWaitingApprovalNotification extends Notification
{
    use Queueable;

    public $quotation;
    public $creator;

    /**
     * Create a new notification instance.
     */
    public function __construct($quotation, $creator)
    {
        $this->quotation = $quotation;
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
        $this->quotation->loadMissing(['customer', 'items.item']);
        
        $itemsCount = $this->quotation->items->count();
        $itemsSummary = $this->quotation->items->take(2)->map(function($i) { 
            return $i->qty . 'x ' . ($i->item->name ?? 'Item'); 
        })->implode(', ') . ($itemsCount > 2 ? ', dll (' . $itemsCount . ' SKU)' : '');

        return [
            'title' => 'Persetujuan Quotation',
            'message' => "Quotation baru <b>" . e($this->quotation->quotation_number) . "</b> diajukan oleh " . e($this->creator->name) . " dan menunggu persetujuan.",
            'icon' => 'document-text',
            'color' => 'text-amber-500',
            'url' => route('sales.quotations.index') . '?show_approval=' . $this->quotation->id,
            'action_type' => 'quotation_approval',
            'quotation_id' => $this->quotation->id,
            'customer_name' => $this->quotation->customer->name ?? 'Pelanggan',
            'total_amount' => $this->quotation->total_amount,
            'items_summary' => $itemsSummary,
        ];
    }
}
