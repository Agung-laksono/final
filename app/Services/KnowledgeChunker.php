<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Services\KnowledgeFormatter;

class KnowledgeChunker
{
    /**
     * Return array of ['chunk_index', 'chunk_type', 'content_text'] untuk satu model.
     * Model kecil -> 1 chunk saja. Model besar (SO/PO) -> dipecah jadi header + items.
     */
    public static function chunk(Model $model): array
    {
        $class = get_class($model);
        
        // Model besar yang perlu chunking
        $bigModels = [
            'Modules\Sales\Models\SalesOrder',
            'Modules\Purchase\Models\PurchaseOrder',
            'Modules\Finance\Models\FinanceTransaction',
        ];
        
        if (!in_array($class, $bigModels)) {
            // Model kecil: satu chunk saja
            return [[
                'chunk_index' => 0,
                'chunk_type'  => 'full',
                'content_text' => KnowledgeFormatter::format($model),
            ]];
        }
        
        // Model besar: pecah jadi beberapa chunk
        return static::chunkLargeModel($model, $class);
    }
    
    protected static function chunkLargeModel(Model $model, string $class): array
    {
        $chunks = [];
        
        if ($class === 'Modules\Sales\Models\SalesOrder') {
            $model->loadMissing(['customer', 'items.item', 'courierVendor']);
            
            // Chunk 0: Header SO (tanpa detail items)
            $chunks[] = [
                'chunk_index' => 0,
                'chunk_type'  => 'header',
                'content_text' => "Sales Order {$model->so_number} dari pelanggan {$model->customer->name} " .
                    "tanggal " . date('d-m-Y', strtotime($model->order_date ?? $model->created_at)) .
                    ". Status: {$model->status}. Payment: {$model->payment_status}. " .
                    "Total: Rp " . number_format($model->grand_total ?? 0, 0, ',', '.') . ".",
            ];
            
            // Chunk 1: Detail items (dipisah agar embedding lebih fokus pada produk)
            if ($model->items && $model->items->count() > 0) {
                $itemTexts = $model->items->map(function($item, $i) {
                    return ($i+1) . ". " . ($item->item->name ?? $item->item_name) .
                        " x{$item->qty} @ Rp " . number_format($item->unit_price ?? 0, 0, ',', '.');
                })->implode('; ');
                
                $chunks[] = [
                    'chunk_index' => 1,
                    'chunk_type'  => 'items',
                    'content_text' => "Daftar barang pada SO {$model->so_number}: {$itemTexts}.",
                ];
            }
        }
        
        // Fallback: kalau tidak ada chunking spesifik, gunakan full formatter
        if (empty($chunks)) {
            $chunks[] = [
                'chunk_index' => 0,
                'chunk_type'  => 'full',
                'content_text' => KnowledgeFormatter::format($model),
            ];
        }
        
        return $chunks;
    }
}
