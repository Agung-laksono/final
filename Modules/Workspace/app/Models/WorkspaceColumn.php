<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkspaceColumn extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'title',
        'color',
        'position',
        'parent_id',
        'type',
        'is_hidden'
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function parent()
    {
        return $this->belongsTo(WorkspaceColumn::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(WorkspaceColumn::class, 'parent_id')->orderBy('position');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }
}
