<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification
{
    use Queueable;

    public $orderNumber;
    public $amount;
    public $rejector;
    public $reason;
    public $type;

    /**
     * Create a new notification instance.
     */
    public function __construct($orderNumber, $amount, $rejector, $reason, $type = 'sales')
    {
        $this->orderNumber = $orderNumber;
        $this->amount = $amount;
        $this->rejector = $rejector;
        $this->reason = $reason;
        $this->type = $type;
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
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Pembayaran Ditolak',
            'message' => "Pembayaran untuk " . ($this->type === 'sales' ? 'SO ' : 'PO ') . " <b>" . e($this->orderNumber) . "</b> sebesar Rp " . number_format($this->amount, 0, ',', '.') . " telah ditolak oleh Keuangan dengan alasan: <i>" . e($this->reason) . "</i>.",
            'icon' => 'x-circle',
            'color' => 'text-red-500',
            'url' => $this->type === 'sales' ? route('sales.orders.index') : route('purchase.orders.kanban'),
        ];
    }
}
