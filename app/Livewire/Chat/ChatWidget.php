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
    public ?string $activeConversationId = null;
    public string $messageInput = '';
    public string $searchUser = '';
    public int $refreshKey = 0;
    
    // Pagination state
    public int $messageLimit = 30;

    public $attachment = null; // Temp untuk image-cropper
    public array $attachments = []; // Menyimpan multi-gambar

    // Group creation
    public bool $showGroupModal = false;
    public string $groupName = '';
    public array $selectedGroupMembers = [];

    // -------------------------------------------------------
    // Hooks
    // -------------------------------------------------------

    public function updatedAttachment($value)
    {
        if ($value) {
            $this->attachments[] = $value;
            $this->attachment = null; // Reset agar cropper bisa dipakai lagi
        }
    }

    public function removeAttachment($index)
    {
        if (isset($this->attachments[$index])) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments); // Re-index
        }
    }

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
            ->take($this->messageLimit)
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
    public function otherLastReadAt()
    {
        if (! $this->activeConversationId) return null;

        $lastRead = $this->activeConversation?->members
            ->where('id', '!=', auth()->id())
            ->max('pivot.last_read_at');
            
        return $lastRead ? \Carbon\Carbon::parse($lastRead) : null;
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
        $this->messageLimit = 30; // Reset limit saat pindah chat
        $this->markAsRead();

        $this->dispatch('chat-scroll-bottom');
    }

    public function loadMore(): void
    {
        $this->messageLimit += 30;
    }

    public function selectConversation(string $conversationId): void
    {
        $this->activeConversationId = $conversationId;
        $this->messageLimit = 30; // Reset limit saat pindah chat
        $this->markAsRead();
        $this->dispatch('chat-scroll-bottom');
    }

    public function backToList(): void
    {
        $this->activeConversationId = null;
    }

    public function sendMessage(): void
    {
        $hasText = trim($this->messageInput) !== '';
        $hasAttachments = count($this->attachments) > 0;

        if ((!$hasText && !$hasAttachments) || ! $this->activeConversationId) {
            return;
        }

        $conv = ChatConversation::find($this->activeConversationId);
        if (! $conv) return;

        $messagesToSend = [];

        // Kasus 1: Punya gambar (bisa satu atau banyak)
        if ($hasAttachments) {
            foreach ($this->attachments as $index => $base64Image) {
                if (is_string($base64Image) && preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
                    $data = substr($base64Image, strpos($base64Image, ',') + 1);
                    $ext = strtolower($matches[1]);
                    if ($ext === 'jpeg') $ext = 'jpg';
                    
                    $data = base64_decode($data);
                    $fileName = 'chat_attachments/' . uniqid() . '.' . $ext;
                    
                    \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $data);
                    $attachmentUrl = '/storage/' . $fileName;

                    $messagesToSend[] = [
                        'type' => 'image',
                        'attachment_url' => $attachmentUrl,
                        // Taruh caption teks hanya di gambar pertama
                        'body' => ($index === 0 && $hasText) ? trim($this->messageInput) : null,
                    ];
                }
            }
        } 
        // Kasus 2: Hanya teks tanpa gambar
        else {
            $messagesToSend[] = [
                'type' => 'text',
                'attachment_url' => null,
                'body' => trim($this->messageInput),
            ];
        }

        foreach ($messagesToSend as $msgData) {
            $message = $conv->messages()->create([
                'sender_id'      => auth()->id(),
                'body'           => $msgData['body'],
                'type'           => $msgData['type'],
                'attachment_url' => $msgData['attachment_url'],
            ]);

            // Broadcast via Pusher Channels
            broadcast(new InternalMessageSent($message));

            // Kirim Pusher Beams push notification ke anggota lain
            $this->sendBeamsNotification($conv, $message);
        }

        $conv->update(['last_message_at' => now()]);

        // Reset input
        $this->messageInput = '';
        $this->attachments = [];
        $this->attachment = null;

        // Update pivot last_read_at untuk sender
        $conv->members()->updateExistingPivot(auth()->id(), ['last_read_at' => now()]);

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

        broadcast(new \App\Events\InternalConversationRead($this->activeConversationId, auth()->id()));
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
