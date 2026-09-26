<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class KeyResult extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'start_value'   => 'float',
        'target_value'  => 'float',
        'current_value' => 'float',
    ];

    public function objective()
    {
        return $this->belongsTo(Objective::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'key_result_task')
                    ->withPivot('contribution')
                    ->withTimestamps();
    }

    public function kpis()
    {
        return $this->hasMany(Kpi::class);
    }

    /**
     * Progress in percentage (0–100)
     */
    public function getProgressAttribute(): float
    {
        $range = $this->target_value - $this->start_value;
        if ($range == 0) return 0;

        $current = $this->current_value - $this->start_value;
        return round(min(max($current / $range * 100, 0), 100), 1);
    }

    /**
     * Recalculate current_value based on linked completed tasks
     */
    public function recalculate(): void
    {
        if ($this->type === 'boolean') return;

        $completedTasks = $this->tasks()
            ->whereNotNull('completed_at')
            ->withPivot('contribution')
            ->get();

        $total = $completedTasks->sum(fn($t) => $t->pivot->contribution);
        $this->update(['current_value' => $total]);
    }
}
