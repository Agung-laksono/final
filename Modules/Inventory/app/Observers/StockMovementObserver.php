<?php

namespace Modules\Inventory\Observers;

use Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Notifications\AbnormalMovementNotification;
use Illuminate\Support\Str;

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

        // --- SISTEM NOTIFIKASI (dengan deduplication via Cache) ---
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
            // THROTTLE: notifikasi gerakan per-item per-5-menit
            // Mencegah spam saat scan massal (scan 20 barang = tetap 1 notifikasi)
            $type = $movement->quantity > 0 ? 'Masuk' : 'Keluar';
            $notifType = abs($movement->quantity) >= 10 ? 'abnormal' : 'normal';
            $movementCacheKey = "notif_movement_{$notifType}_{$movement->item_id}_{$type}";

            if (!Cache::has($movementCacheKey)) {
                // Tandai sudah dikirim, kunci selama 5 menit
                Cache::put($movementCacheKey, true, now()->addMinutes(5));

                if ($notifType === 'abnormal') {
                    Notification::send($recipients, new AbnormalMovementNotification($item, abs($movement->quantity), $type, $userName, $userAvatar));
                } elseif (abs($movement->quantity) > 0) {
                    Notification::send($recipients, new \App\Notifications\NormalMovementNotification($item, abs($movement->quantity), $type, $userName, $userAvatar));
                }
            }
            // else: notifikasi diabaikan karena masih dalam jendela 5 menit yang sama

            // 2. Deteksi Low Stock (Hanya jika stok berkurang)
            // THROTTLE: notifikasi low-stock per-item per-30-menit
            // Mencegah notifikasi berulang setiap kali ada pengurangan stok kecil
            if ($movement->quantity < 0 && $item->min_stock > 0) {
                $totalStock = DB::table('item_warehouse')
                    ->where('item_id', $movement->item_id)
                    ->sum('stock');
                    
                if ($totalStock < $item->min_stock) {
                    $lowStockCacheKey = "notif_low_stock_{$movement->item_id}";

                    if (!Cache::has($lowStockCacheKey)) {
                        // Tandai sudah dikirim, kunci selama 30 menit
                        Cache::put($lowStockCacheKey, true, now()->addMinutes(30));

                        Notification::send($recipients, new LowStockNotification($item, $totalStock));
                        
                        // --- OTO-CREATE INVENTORY REQUEST ---
                        $existingRequest = \Modules\Inventory\Models\InventoryRequest::where('item_id', $movement->item_id)
                            ->whereIn('status', ['draft', 'review'])
                            ->exists();
                            
                        if (!$existingRequest) {
                            $qtyToOrder = $item->max_stock > 0 
                                ? max($item->max_stock - $totalStock, $item->min_stock)
                                : $item->min_stock * 2;
                                
                            \Modules\Inventory\Models\InventoryRequest::create([
                                'item_id' => $movement->item_id,
                                'source_type' => 'low_stock',
                                'reference_number' => 'REQ-' . strtoupper(Str::random(6)),
                                'requested_qty' => $qtyToOrder,
                                'notes' => 'Sistem Otomatis: Stok menipis (' . $totalStock . ' dari batas minimal ' . $item->min_stock . ').',
                                'status' => 'draft'
                            ]);
                            
                            \App\Events\KanbanUpdated::safeDispatch('inventory_request');
                        }
                    }
                }
            }
        }
    }
}
