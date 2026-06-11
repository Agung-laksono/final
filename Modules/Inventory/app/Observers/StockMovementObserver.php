<?php

namespace Modules\Inventory\Observers;

use Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Notifications\AbnormalMovementNotification;

class StockMovementObserver
{
    /**
     * Handle the StockMovement "creating" event.
     */
    public function creating(StockMovement $movement): void
    {
        // Dapatkan stok terakhir di gudang saat ini
        $currentStock = DB::table('item_warehouse')
            ->where('item_id', $movement->item_id)
            ->where('warehouse_id', $movement->warehouse_id)
            ->value('stock') ?? 0;

        $movement->stock_before = $currentStock;
        
        // Kita asumsikan 'quantity' dari pemanggil sudah bernilai + atau -
        // berdasarkan jenis transaksi.
        $movement->stock_after = $currentStock + $movement->quantity;
    }

    /**
     * Handle the StockMovement "created" event.
     */
    public function created(StockMovement $movement): void
    {
        // Pengecekan eksplisit agar kompatibel dengan SQLite dan timestamp
        $exists = DB::table('item_warehouse')
            ->where('warehouse_id', $movement->warehouse_id)
            ->where('item_id', $movement->item_id)
            ->exists();

        if ($exists) {
            DB::table('item_warehouse')
                ->where('warehouse_id', $movement->warehouse_id)
                ->where('item_id', $movement->item_id)
                ->update([
                    'stock' => $movement->stock_after,
                    'updated_at' => now()
                ]);
        } else {
            DB::table('item_warehouse')->insert([
                'warehouse_id' => $movement->warehouse_id,
                'item_id' => $movement->item_id,
                'stock' => $movement->stock_after,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // --- SISTEM NOTIFIKASI ---
        $recipients = User::permission('inventory.notifikasi.view')
            ->orWhereHas('roles', fn($q) => $q->where('name', 'Super Admin'))
            ->get();
        $item = $movement->item;
        $userName = $movement->user ? $movement->user->name : 'Sistem';
        
        // Generate UI Avatar atau gunakan avatar asli jika ada
        $userAvatar = $movement->user && $movement->user->avatar 
            ? \Illuminate\Support\Facades\Storage::url($movement->user->avatar) 
            : 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&color=FFFFFF&background=09090b';

        if ($recipients->isNotEmpty() && $item) {
            // 1. Deteksi Abnormal/Normal Movement
            $type = $movement->quantity > 0 ? 'Masuk' : 'Keluar';
            if (abs($movement->quantity) >= 10) {
                Notification::send($recipients, new AbnormalMovementNotification($item, abs($movement->quantity), $type, $userName, $userAvatar));
            } elseif (abs($movement->quantity) > 0) {
                Notification::send($recipients, new \App\Notifications\NormalMovementNotification($item, abs($movement->quantity), $type, $userName, $userAvatar));
            }

            // 2. Deteksi Low Stock (Hanya jika stok berkurang)
            if ($movement->quantity < 0 && $item->min_stock > 0) {
                $totalStock = DB::table('item_warehouse')
                    ->where('item_id', $movement->item_id)
                    ->sum('stock');
                    
                if ($totalStock < $item->min_stock) {
                    Notification::send($recipients, new LowStockNotification($item, $totalStock));
                }
            }
        }
    }
}
