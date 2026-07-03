<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProductionOrderFactory;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function item()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Item::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(\Modules\Purchase\Models\PurchaseOrder::class, 'purchase_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function histories()
    {
        return $this->hasMany(ProductionOrderHistory::class);
    }

    public function getCompletedPhasesAttribute()
    {
        $completedPhases = [];
        
        if ($this->notes) {
            if (preg_match_all('/\[History:\s*(.*?)\s*\|\s*(.*?)\]/', $this->notes, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $completedPhases[] = [
                        'phase' => $match[1],
                        'vendor' => $match[2],
                    ];
                }
            }
            if (preg_match_all('/\[Ex:\s*(.*?)\]/', $this->notes, $matchesEx)) {
                foreach ($matchesEx[1] as $phase) {
                    $completedPhases[] = [
                        'phase' => $phase,
                        'vendor' => 'Unknown',
                    ];
                }
            }
        }
        
        return $completedPhases;
    }

    public function getPhaseColorAttribute()
    {
        if (!$this->phase_type) return 'zinc';
        
        return match($this->phase_type) {
            'finishing' => 'purple',
            'jok' => 'blue',
            'rakit' => 'amber',
            default => 'zinc'
        };
    }

    // protected static function newFactory(): ProductionOrderFactory
    // {
    //     // return ProductionOrderFactory::new();
    // }
}
