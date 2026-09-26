<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Objective extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'year'       => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function keyResults()
    {
        return $this->hasMany(KeyResult::class);
    }

    /**
     * Overall progress: average of all key results' progress
     */
    public function getProgressAttribute(): float
    {
        $krs = $this->keyResults;
        if ($krs->isEmpty()) return 0;
        return round($krs->sum(fn($kr) => $kr->progress) / $krs->count(), 1);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'achieved' => 'green',
            'on_track' => 'blue',
            'at_risk'  => 'yellow',
            'failed'   => 'red',
            default    => 'zinc',
        };
    }

    /**
     * Auto-calculate status from progress.
     * Skips update if status is already manually locked to 'achieved' or 'failed'.
     */
    public function syncAutoStatus(): void
    {
        // Don't override manually set terminal statuses
        if (in_array($this->status, ['achieved', 'failed'])) return;

        $progress = $this->getProgressAttribute();

        $autoStatus = match(true) {
            $progress >= 100 => 'achieved',
            $progress >= 70  => 'on_track',
            $progress >= 30  => 'at_risk',
            default          => 'draft',
        };

        if ($this->status !== $autoStatus) {
            $this->updateQuietly(['status' => $autoStatus]);
        }
    }
}
