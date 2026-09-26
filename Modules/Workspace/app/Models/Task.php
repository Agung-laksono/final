<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Workspace\Database\Factories\TaskFactory;

class Task extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'last_significant_update_at' => 'datetime',
    ];
    public function labels() { return $this->belongsToMany(TaskLabel::class, 'task_label'); }
    public function subtasks() { return $this->hasMany(TaskSubtask::class); }
    public function comments() { return $this->hasMany(TaskComment::class); }
    public function attachments() { return $this->hasMany(TaskAttachment::class); }
    public function urls() { return $this->hasMany(TaskUrl::class); }
    public function activities() { return $this->hasMany(TaskActivity::class); }
    public function timeLogs() { return $this->hasMany(TaskTimeLog::class); }
    public function assignees() { return $this->belongsToMany(\App\Models\User::class, 'task_assignee'); }
    public function userReads() { return $this->belongsToMany(\App\Models\User::class, 'task_user_reads')->withPivot('last_read_at'); }
    public function linkedFrom() { return $this->hasMany(TaskLink::class, 'to_project_id'); }
    public function linkedTo() { return $this->hasMany(TaskLink::class, 'from_project_id'); }

    public function workspace() { return $this->belongsTo(Workspace::class); }
    public function workspaceColumn() { return $this->belongsTo(WorkspaceColumn::class); }
    public function keyResults() { return $this->belongsToMany(KeyResult::class, 'key_result_task')->withPivot('contribution')->withTimestamps(); }

    // protected static function newFactory(): TaskFactory
    // {
    //     // return TaskFactory::new();
    // }
}
