<?php

use function Livewire\Volt\{state, on, mount, with};
use Illuminate\Notifications\DatabaseNotification;

state(['unreadCount' => 0]);

$getNotifications = function () {
    if (!auth()->check()) {
        return collect([]);
    }
    
    $user = auth()->user();
    $this->unreadCount = $user->unreadNotifications()->count();
    
    // Ambil 5 notifikasi terbaru
    return $user->notifications()->take(5)->get();
};

$markAsRead = function ($notificationId) {
    if (!auth()->check()) return;
    
    $notification = auth()->user()->notifications()->find($notificationId);
    if ($notification) {
        $notification->markAsRead();
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
    }
};

$markAllAsRead = function () {
    if (!auth()->check()) return;
    
    auth()->user()->unreadNotifications->markAsRead();
    $this->unreadCount = 0;
};

// Polling interval (opsional, jika tidak pakai pusher)
// Namun jika Pusher ada, kita bisa menggunakan Echo untuk mendengarkan event Notifikasi bawaan Laravel
on(['echo-private:App.Models.User.{auth()->id()},.Illuminate\Notifications\Events\BroadcastNotificationCreated' => function () {
    $this->unreadCount = auth()->user()->unreadNotifications()->count();
}]);

with(fn () => [
    'notifications' => $this->getNotifications()
]);

?>

<flux:dropdown position="top" align="start">
    <flux:sidebar.item icon="bell" class="relative cursor-pointer w-full text-start" data-flux-sidebar-action>
        {{ __('Notifikasi') }}
        @if ($unreadCount > 0)
            <span class="absolute right-3 top-1/2 -translate-y-1/2 flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 border border-white dark:border-zinc-900"></span>
            </span>
        @endif
    </flux:sidebar.item>

    <flux:menu class="w-80 sm:w-96 p-0 overflow-hidden">
        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/50">
            <h3 class="font-semibold text-sm text-zinc-800 dark:text-zinc-200">{{ __('Notifikasi') }}</h3>
            @if ($unreadCount > 0)
                <button wire:click="markAllAsRead" class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium">Tandai semua dibaca</button>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto" wire:poll.30s>
            @forelse ($notifications as $notification)
                <div class="group relative flex gap-4 p-4 border-b border-zinc-100 dark:border-zinc-800 last:border-0 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ $notification->read_at ? 'opacity-70' : 'bg-blue-50/30 dark:bg-blue-900/10' }}">
                    <div class="shrink-0 mt-1">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 ring-1 ring-zinc-200 dark:ring-zinc-700">
                            @if(isset($notification->data['icon']))
                                <flux:icon :icon="$notification->data['icon']" class="h-4 w-4 {{ $notification->data['color'] ?? 'text-zinc-500' }}" />
                            @else
                                <flux:icon.bell class="h-4 w-4 text-zinc-500" />
                            @endif
                        </div>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-0.5">
                            {{ $notification->data['title'] ?? 'Notifikasi' }}
                        </p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 break-words mb-1">
                            {{ $notification->data['message'] ?? '' }}
                        </p>
                        <p class="text-[11px] text-zinc-400 font-medium">
                            {{ $notification->created_at->diffForHumans() }}
                        </p>
                        
                        @if (!$notification->read_at)
                            <div class="absolute right-4 top-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="markAsRead('{{ $notification->id }}')" class="p-1 rounded-full hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition" title="Tandai sudah dibaca">
                                    <flux:icon.check class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        @endif
                    </div>
                    
                    @if (!$notification->read_at)
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-blue-500 rounded-r"></div>
                    @endif
                </div>
            @empty
                <div class="py-8 text-center flex flex-col items-center">
                    <flux:icon.bell-slash class="h-8 w-8 text-zinc-300 dark:text-zinc-600 mb-3" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Belum ada notifikasi baru</p>
                </div>
            @endforelse
        </div>
        
        @if ($notifications->count() > 0)
            <div class="border-t border-zinc-200 dark:border-zinc-700 p-2 text-center bg-zinc-50 dark:bg-zinc-800/50">
                <a href="#" class="text-xs font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300">
                    Lihat semua notifikasi
                </a>
            </div>
        @endif
    </flux:menu>
</flux:dropdown>
