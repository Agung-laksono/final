<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Kpi extends Model
{
    use HasFactory;

    protected $table = 'kpis';

    protected $guarded = [];

    protected $casts = [
        'target_value'  => 'float',
        'current_value' => 'float',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function keyResult()
    {
        return $this->belongsTo(KeyResult::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Achievement percentage
     */
    public function getProgressAttribute(): float
    {
        if ($this->target_value == 0) return 0;
        $progress = ($this->current_value / $this->target_value) * 100;
        return round(min($progress, 100), 1);
    }

    public function getStatusColorAttribute(): string
    {
        $p = $this->progress;
        if ($p >= 100) return 'green';
        if ($p >= 70)  return 'blue';
        if ($p >= 40)  return 'yellow';
        return 'red';
    }
}
