<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSubmittedNotification extends Notification
{
    use Queueable;

    public $orderNumber;
    public $amount;
    public $creator;
    public $type; // 'sales' or 'purchase'
    public $paymentMethod;
    public $customerName;

    /**
     * Create a new notification instance.
     */
    public function __construct($orderNumber, $amount, $creator, $type = 'sales', $paymentMethod = 'Transfer', $customerName = 'Pelanggan/Vendor')
    {
        $this->orderNumber = $orderNumber;
        $this->amount = $amount;
        $this->creator = $creator;
        $this->type = $type;
        $this->paymentMethod = $paymentMethod;
        $this->customerName = $customerName;
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
        $title = "Validasi Pembayaran: " . $this->orderNumber;
        $message = "Terdapat pembayaran sebesar Rp " . number_format($this->amount, 0, ',', '.') . " dari " . e($this->customerName) . " (via " . e(ucfirst($this->paymentMethod)) . "). Mohon segera cek mutasi dan validasi.";
        
        return [
            'title' => $title,
            'message' => $message,
            'icon' => 'currency-dollar',
            'color' => 'text-amber-500',
            'url' => route('finance.inbox'),
        ];
    }
}
