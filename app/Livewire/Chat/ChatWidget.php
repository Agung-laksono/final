<?php

namespace App\Livewire\Chat;

use App\Events\InternalMessageSent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ChatWidget extends Component
{
    // -------------------------------------------------------
    // State
    // -------------------------------------------------------
    public bool $isOpen = false;
    public int $refreshKey = 0; // Trigger re-render on new messages
    public ?string $activeConversationId = null;
    public string $messageInput = '';
    public string $searchUser = '';

    // Group creation
    public bool $showGroupModal = false;
    public string $groupName = '';
    public array $selectedGroupMembers = [];

    // -------------------------------------------------------
    // Computed
    // -------------------------------------------------------

    #[Computed]
    public function conversations()
    {
        $userId = auth()->id();

        return ChatConversation::with(['members', 'latestMessage.sender'])
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function ($conv) use ($userId) {
                $conv->unread  = $conv->unreadCount($userId);
                $conv->display = $conv->displayName($userId);
                // Avatar: direct → other user avatar, group → null (icon)
                $otherUser = $conv->type === 'direct' ? $conv->otherUser($userId) : null;
                $conv->avatar = $otherUser?->avatarUrl() ?? null;
                $conv->other_user_id = $otherUser?->id ?? null;
                return $conv;
            });
    }

    #[Computed]
    public function messages()
    {
        if (! $this->activeConversationId) {
            return collect();
        }

        return ChatMessage::with('sender')
            ->where('chat_conversation_id', $this->activeConversationId)
            ->latest()
            ->take(60)
            ->get()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function activeConversation()
    {
        if (! $this->activeConversationId) return null;

        return $this->conversations->firstWhere('id', $this->activeConversationId);
    }

    #[Computed]
    public function totalUnread(): int
    {
        return $this->conversations->sum('unread');
    }

    #[Computed]
    public function searchResults()
    {
        if (strlen($this->searchUser) < 2) return collect();

        return User::where('id', '!=', auth()->id())
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchUser . '%')
                  ->orWhere('email', 'like', '%' . $this->searchUser . '%');
            })
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function allUsers()
    {
        return User::where('id', '!=', auth()->id())->orderBy('name')->get();
    }

    // -------------------------------------------------------
    // Actions
    // -------------------------------------------------------

    public function toggleOpen(): void
    {
        $this->isOpen = ! $this->isOpen;

        if ($this->isOpen && $this->activeConversationId) {
            $this->markAsRead();
        }
    }

    public function openDirectChat(int $userId): void
    {
        $authId = auth()->id();

        // Cari existing direct conversation
        $conv = ChatConversation::findDirectBetween($authId, $userId);

        if (! $conv) {
            $conv = ChatConversation::createDirect($authId, $userId);
        }

        $this->activeConversationId = $conv->id;
        $this->isOpen = true;
        $this->searchUser = '';
        $this->markAsRead();

        $this->dispatch('chat-scroll-bottom');
    }

    public function selectConversation(string $conversationId): void
    {
        $this->activeConversationId = $conversationId;
        $this->markAsRead();
        $this->dispatch('chat-scroll-bottom');
    }

    public function backToList(): void
    {
        $this->activeConversationId = null;
    }

    public function sendMessage(): void
    {
        if (trim($this->messageInput) === '' || ! $this->activeConversationId) {
            return;
        }

        $conv = ChatConversation::find($this->activeConversationId);
        if (! $conv) return;

        $message = $conv->messages()->create([
            'sender_id' => auth()->id(),
            'body'      => trim($this->messageInput),
            'type'      => 'text',
        ]);

        $conv->update(['last_message_at' => now()]);

        // Reset input
        $this->messageInput = '';

        // Update pivot last_read_at untuk sender
        $conv->members()->updateExistingPivot(auth()->id(), ['last_read_at' => now()]);

        // Broadcast via Pusher Channels
        broadcast(new InternalMessageSent($message));

        // Kirim Pusher Beams push notification ke anggota lain
        $this->sendBeamsNotification($conv, $message);

        $this->dispatch('chat-scroll-bottom');
        $this->dispatch('chat-message-sent');
    }

    protected function sendBeamsNotification(ChatConversation $conv, ChatMessage $message): void
    {
        try {
            $beams = app(\App\Services\BeamsService::class);
            $senderName = auth()->user()->name;
            $authId = auth()->id();

            $body = strlen($message->body) > 60
                ? substr($message->body, 0, 57) . '...'
                : $message->body;

            // Kirim ke setiap anggota selain sender
            $conv->members->where('id', '!=', $authId)->each(function ($u) use ($beams, $senderName, $body, $conv) {
                $beams->sendToUser(
                    $u->id,
                    $senderName,
                    $body,
                    ['conversation_id' => $conv->id],
                    '/'
                );
            });
        } catch (\Exception $e) {
            logger()->warning('ChatWidget Beams Error: ' . $e->getMessage());
        }
    }

    public function markAsRead(): void
    {
        if (! $this->activeConversationId) return;

        $conv = ChatConversation::find($this->activeConversationId);
        if (! $conv) return;

        $conv->members()->updateExistingPivot(auth()->id(), [
            'last_read_at' => now(),
        ]);
    }

    // Group creation
    public function toggleGroupMember(int $userId): void
    {
        if (in_array($userId, $this->selectedGroupMembers)) {
            $this->selectedGroupMembers = array_values(
                array_filter($this->selectedGroupMembers, fn ($id) => $id !== $userId)
            );
        } else {
            $this->selectedGroupMembers[] = $userId;
        }
    }

    public function createGroup(): void
    {
        $this->validate([
            'groupName'            => 'required|string|max:100',
            'selectedGroupMembers' => 'required|array|min:1',
        ]);

        $conv = ChatConversation::createGroup(
            $this->groupName,
            auth()->id(),
            $this->selectedGroupMembers
        );

        $this->showGroupModal = false;
        $this->groupName = '';
        $this->selectedGroupMembers = [];

        $this->selectConversation($conv->id);
    }

    // -------------------------------------------------------
    // Real-time Listeners
    // -------------------------------------------------------

    /**
     * handleNewMessage dipanggil dari Alpine.js (via $wire.call) saat Pusher
     * mengirim event baru ke channel private. Ini lebih reliable daripada
     * getListeners() karena listener diregistrasi secara dinamis di Alpine.
     */
    public function handleNewMessage(array $event): void
    {
        // Tandai sebagai dibaca jika pesan dari orang lain
        if (($event['sender_id'] ?? null) != auth()->id()) {
            $this->markAsRead();
        }

        // Increment refreshKey → paksa Livewire re-render → #[Computed] recalculate
        $this->refreshKey++;
        $this->dispatch('chat-scroll-bottom');
    }

    /**
     * Dipanggil dari Alpine.js saat ada pesan di conversation LAIN
     * (untuk update badge unread di conversation list).
     */
    public function refreshConversations(): void
    {
        $this->refreshKey++;
    }

    // -------------------------------------------------------
    // Render
    // -------------------------------------------------------

    public function render()
    {
        return view('livewire.chat.chat-widget');
    }
}
