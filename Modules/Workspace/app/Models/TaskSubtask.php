<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Workspace\Database\Factories\TaskSubtaskFactory;

class TaskSubtask extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
        protected $guarded = [];
        protected $casts = [
            'is_completed' => 'boolean',
            'requires_input' => 'boolean',
        ];
    public function project() { return $this->belongsTo(Task::class, 'task_id'); }

    public function parent() {
        return $this->belongsTo(TaskSubtask::class, 'parent_id');
    }

    public function children() {
        return $this->hasMany(TaskSubtask::class, 'parent_id')->orderBy('position', 'asc')->orderBy('created_at', 'asc');
    }
    // {
    //     // return TaskSubtaskFactory::new();
    // }
}
