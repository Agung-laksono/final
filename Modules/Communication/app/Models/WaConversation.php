<?php

namespace Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class WaConversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'phone_number',
        'name',
        'last_message_at',
        'unread_count',
        'is_archived',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class, 'wa_conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(WaMessage::class, 'wa_conversation_id')->latestOfMany();
    }
}
