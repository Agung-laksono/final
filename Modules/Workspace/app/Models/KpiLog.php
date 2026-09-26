<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Workspace\Database\Factories\KpiLogFactory;

class KpiLog extends Model
{
    use HasFactory;

    protected $fillable = ['kpi_id', 'user_id', 'old_value', 'new_value', 'notes'];
    
    protected $casts = [
        'old_value' => 'float',
        'new_value' => 'float',
    ];

    public function kpi()
    {
        return $this->belongsTo(Kpi::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
