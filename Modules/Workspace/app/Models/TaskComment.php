<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Workspace\Database\Factories\TaskCommentFactory;

class TaskComment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
        protected $guarded = [];
    public function project() { return $this->belongsTo(Task::class); }
    public function user() { return $this->belongsTo(\App\Models\User::class); }
    public function parent() { return $this->belongsTo(TaskComment::class, 'parent_id'); }
    public function replies() { return $this->hasMany(TaskComment::class, 'parent_id'); }

    // protected static function newFactory(): TaskCommentFactory
    // {
    //     // return TaskCommentFactory::new();
    // }
}
