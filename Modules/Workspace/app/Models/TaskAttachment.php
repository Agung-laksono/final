<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Workspace\Database\Factories\TaskAttachmentFactory;

class TaskAttachment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
        protected $guarded = [];
    public function project() { return $this->belongsTo(Task::class); }
    public function user() { return $this->belongsTo(\App\Models\User::class); }

    // protected static function newFactory(): TaskAttachmentFactory
    // {
    //     // return TaskAttachmentFactory::new();
    // }
}
