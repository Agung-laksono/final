<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Internal Chat Widget: Private channel per conversation
// Hanya anggota conversation yang dapat subscribe
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    return \App\Models\ChatConversation::where('id', $conversationId)
        ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});

// Presence channel untuk melacak user online
Broadcast::channel('chat-presence', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});
