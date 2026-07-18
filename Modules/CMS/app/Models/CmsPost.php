<?php

namespace Modules\CMS\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role;

class CmsPost extends Model
{
    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'cms_post_roles', 'post_id', 'role_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cms_post_users', 'post_id', 'user_id');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cms_post_reads', 'post_id', 'user_id')
            ->withPivot('read_at');
    }
}
