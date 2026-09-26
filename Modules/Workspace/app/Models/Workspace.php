<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'cover_image',
        'is_active',
    ];
    
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function columns()
    {
        return $this->hasMany(WorkspaceColumn::class)->orderBy('position');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
    
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function objectives()
    {
        return $this->hasMany(Objective::class)->orderByDesc('year')->orderBy('period');
    }

    public function kpis()
    {
        return $this->hasMany(Kpi::class);
    }
}
