<?php

namespace Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class WaMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'wa_conversation_id',
        'fonnte_id',
        'state_id',
        'direction', // 'in' or 'out'
        'message_type', // 'text', 'image', 'document', 'audio', 'video'
        'message',
        'media_url',
        'status', // 'pending', 'sent', 'delivered', 'read', 'failed'
        'error_message',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WaConversation::class, 'wa_conversation_id');
    }
}
