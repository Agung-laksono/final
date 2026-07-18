<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentValidatedNotification extends Notification
{
    use Queueable;

    public $orderNumber;
    public $amount;
    public $validator;
    public $type;

    /**
     * Create a new notification instance.
     */
    public function __construct($orderNumber, $amount, $validator, $type = 'sales')
    {
        $this->orderNumber = $orderNumber;
        $this->amount = $amount;
        $this->validator = $validator;
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
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = $this->type === 'sales' ? 'Pembayaran SO Tervalidasi' : 'Pembayaran PO Tervalidasi';
        $message = "Pembayaran " . ($this->type === 'sales' ? 'SO' : 'PO') . " <b>" . e($this->orderNumber) . "</b> sebesar Rp " . number_format($this->amount, 0, ',', '.') . " telah divalidasi oleh Finance (" . e($this->validator->name) . ").";
        
        return [
            'title' => $title,
            'message' => $message,
            'icon' => 'check-badge',
            'color' => 'text-emerald-500',
            'url' => $this->type === 'sales' ? route('sales.orders.index') : '#',
        ];
    }
}
