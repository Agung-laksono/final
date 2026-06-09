<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ItemUpdatedNotification extends Notification
{
    use Queueable;

    public $item;
    public $actor;
    public $oldName;

    /**
     * Buat instance notifikasi baru.
     * Menerima data barang yang diubah, user yang mengubah, dan nama barang lama.
     */
    public function __construct($item, $actor, $oldName)
    {
        $this->item = $item;
        $this->actor = $actor;
        $this->oldName = $oldName;
    }

    /**
     * Tentukan jalur pengiriman notifikasi.
     * database = disimpan ke tabel notifications
     * broadcast = dikirim secara real-time via Pusher/Websockets
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Format data notifikasi yang akan ditampilkan.
     * 
     * PENTING: Anda dapat dengan mudah mengubah teks pesan, judul, warna,
     * dan ikon notifikasi langsung di dalam fungsi ini!
     */
    public function toArray(object $notifiable): array
    {
        return [
            // 1. UBAH JUDUL NOTIFIKASI DI SINI
            'title' => 'Data Barang Diubah',
            
            // 2. UBAH ISI PESAN NOTIFIKASI DI SINI
            'message' => $this->oldName !== $this->item->name 
                ? e($this->actor->name) . " mengubah nama barang dari <b>" . e($this->oldName) . "</b> menjadi <b>" . e($this->item->name) . "</b>."
                : e($this->actor->name) . " memperbarui rincian data barang <b>" . e($this->item->name) . "</b>.",
            
            // 3. UBAH IKON DI SINI (Gunakan nama ikon dari Heroicons)
            'icon' => 'pencil-square',
            
            // 4. UBAH WARNA IKON DI SINI (Gunakan class warna Tailwind CSS, misal: text-yellow-500)
            'color' => 'text-yellow-500',
            
            // Tautan yang akan dituju saat notifikasi diklik
            'url' => route('inventory') . '?show_item=' . $this->item->id,
        ];
    }
}
