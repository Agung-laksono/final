<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiKnowledgeBase extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'content_text',
        'embedding',
    ];

    public function model()
    {
        return $this->morphTo();
    }
}
