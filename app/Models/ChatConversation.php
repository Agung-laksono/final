<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatConversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'name',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // -------------------------------------------------------
    // Relations
    // -------------------------------------------------------

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_conversation_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /** Untuk direct chat: kembalikan user lawan bicara */
    public function otherUser(int $authId): ?User
    {
        return $this->members->firstWhere('id', '!=', $authId);
    }

    /** Unread count untuk user tertentu */
    public function unreadCount(int $userId): int
    {
        $pivot = $this->members->firstWhere('id', $userId)?->pivot;
        $lastRead = $pivot?->last_read_at;

        $query = $this->messages()->where('sender_id', '!=', $userId);

        if ($lastRead) {
            $query->where('created_at', '>', $lastRead);
        }

        return $query->count();
    }

    /** Display name: group pakai $name, direct pakai nama lawan bicara */
    public function displayName(int $authId): string
    {
        if ($this->type === 'group') {
            return $this->name ?? 'Group Chat';
        }

        return $this->otherUser($authId)?->name ?? 'Unknown';
    }

    /** Cari direct conversation antara dua user */
    public static function findDirectBetween(int $userA, int $userB): ?self
    {
        return self::where('type', 'direct')
            ->whereHas('members', fn ($q) => $q->where('user_id', $userA))
            ->whereHas('members', fn ($q) => $q->where('user_id', $userB))
            ->first();
    }

    /** Buat direct conversation antara dua user */
    public static function createDirect(int $userA, int $userB): self
    {
        $conv = self::create(['type' => 'direct', 'created_by' => $userA]);
        $conv->members()->attach([$userA, $userB]);
        return $conv;
    }

    /** Buat group conversation */
    public static function createGroup(string $name, int $creatorId, array $memberIds): self
    {
        $conv = self::create(['type' => 'group', 'name' => $name, 'created_by' => $creatorId]);
        $conv->members()->attach(array_unique(array_merge([$creatorId], $memberIds)));
        return $conv;
    }
}
