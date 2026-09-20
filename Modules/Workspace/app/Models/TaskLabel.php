<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Workspace\Database\Factories\TaskLabelFactory;

class TaskLabel extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
        protected $guarded = [];
    public function tasks() { return $this->belongsToMany(Task::class, 'task_label'); }

    // protected static function newFactory(): TaskLabelFactory
    // {
    //     // return TaskLabelFactory::new();
    // }
}
