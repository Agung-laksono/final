<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsCategory extends Model
{
    protected $guarded = [];

    public function posts(): HasMany
    {
        return $this->hasMany(CmsPost::class, 'category_id');
    }
}
