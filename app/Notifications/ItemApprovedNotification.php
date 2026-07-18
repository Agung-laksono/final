<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ItemApprovedNotification extends Notification
{
    use Queueable;

    public $item;
    public $approver;

    /**
     * Create a new notification instance.
     */
    public function __construct($item, $approver)
    {
        $this->item = $item;
        $this->approver = $approver;
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
            'title' => 'Barang Disetujui',
            'message' => "Pengajuan barang <b>" . e($this->item->name) . "</b> telah disetujui dan diaktifkan oleh " . e($this->approver->name) . ".",
            'icon' => 'check-circle',
            'color' => 'text-emerald-500',
            'url' => route('inventory.items') . '?show_item=' . $this->item->id,
        ];
    }
}
