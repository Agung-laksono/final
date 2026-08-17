<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public $authId = null;

    public function mount() {
        $this->authId = auth()->id();
    }

    public function with() {
        return [
            'notifications' => $this->getNotifications(),
            'unreadCount'   => auth()->check() ? auth()->user()->unreadNotifications()->count() : 0,
        ];
    }

    public function getNotifications() {
        if (!auth()->check()) return collect([]);
        return auth()->user()->notifications()->take(3)->get();
    }

    public function markAsReadAndRedirect($notificationId, $url) {
        if (!auth()->check()) return;
        $notification = auth()->user()->notifications()->find($notificationId);
        if ($notification) {
            $notification->markAsRead();
        }
        if ($url && $url !== '#') {
            return $this->redirect($url, navigate: true);
        }
    }

    public function markAllAsRead() {
        if (!auth()->check()) return;
        auth()->user()->unreadNotifications->markAsRead();
        \App\Livewire\Support\NotificationSync::syncToOthers(auth()->id(), 0);
    }

    #[On('echo-private:App.Models.User.{authId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated')]
    public function onNotificationReceived() {
        // Re-render component
    }

    #[On('echo-private:App.Models.User.{authId},.NotificationsRead')]
    public function onNotificationsRead() {
        // Re-render component
    }

    #[On('notifications-updated')]
    public function onNotificationsUpdated() {
        // Re-render component
    }
};
?>

<div>
        <div class="flex items-center justify-end mb-2 px-1 gap-3">
            @if($unreadCount > 0)
                <button wire:click="markAllAsRead" class="text-[11px] text-blue-500 hover:text-blue-600 font-medium transition-colors">
                    Tandai semua dibaca
                </button>
            @endif
            <a href="{{ route('notifications.index') }}" wire:navigate class="text-[11px] text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors">
                Lihat semua →
            </a>
        </div>

    {{-- Notification List --}}
    <div class="flex flex-col gap-1.5">
        @forelse($notifications as $notif)
            <div wire:click="markAsReadAndRedirect('{{ $notif->id }}', '{{ $notif->data['url'] ?? '#' }}')"
                 wire:key="gnotif-{{ $notif->id }}"
                 class="flex items-start gap-3 px-3 py-2.5 rounded-xl cursor-pointer transition-all duration-150
                        {{ $notif->read_at
                            ? 'bg-zinc-100/60 dark:bg-zinc-800/40 opacity-60 hover:opacity-100'
                            : 'bg-white dark:bg-zinc-800 shadow-sm border border-zinc-100 dark:border-zinc-700/50' }}">

                {{-- Icon --}}
                <div class="shrink-0 mt-0.5">
                    @if(isset($notif->data['avatar']))
                        <div class="relative">
                            <img src="{{ $notif->data['avatar'] }}" class="w-8 h-8 rounded-full object-cover" />
                            @if(isset($notif->data['icon']))
                                <div class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-white dark:bg-zinc-800 flex items-center justify-center ring-1 ring-zinc-200 dark:ring-zinc-700">
                                    <flux:icon :icon="$notif->data['icon']" class="w-2.5 h-2.5 {{ $notif->data['color'] ?? 'text-zinc-500' }}" />
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="w-8 h-8 rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center">
                            <flux:icon :icon="$notif->data['icon'] ?? 'bell'" class="w-4 h-4 {{ $notif->data['color'] ?? 'text-zinc-500' }}" />
                        </div>
                    @endif
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <p class="text-[12px] leading-snug text-zinc-700 dark:text-zinc-300 line-clamp-2">
                        {!! $notif->data['message'] ?? '' !!}
                    </p>
                    <p class="text-[10px] text-zinc-400 mt-1">{{ $notif->created_at->diffForHumans() }}</p>
                </div>

                {{-- Unread dot --}}
                @if(!$notif->read_at)
                    <div class="w-2 h-2 rounded-full bg-blue-500 shrink-0 mt-1.5"></div>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-6 text-center">
                <flux:icon.bell-slash class="w-6 h-6 text-zinc-300 dark:text-zinc-600 mb-1.5" />
                <p class="text-[12px] text-zinc-400">Tidak ada notifikasi</p>
            </div>
        @endforelse
    </div>
</div>
