<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Workspace\Database\Factories\TaskLinkFactory;

class TaskLink extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
        protected $guarded = [];
    public function fromProject() { return $this->belongsTo(Task::class, 'from_project_id'); }
    public function toProject() { return $this->belongsTo(Task::class, 'to_project_id'); }

    // protected static function newFactory(): TaskLinkFactory
    // {
    //     // return TaskLinkFactory::new();
    // }
}
