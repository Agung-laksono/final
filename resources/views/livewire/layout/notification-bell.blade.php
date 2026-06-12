<?php

use function Livewire\Volt\{state, on, mount, with};
use Illuminate\Notifications\DatabaseNotification;

state(['unreadCount' => 0, 'authId' => null]);

mount(function() {
    $this->authId = auth()->id();
    if (auth()->check()) {
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
    }
});

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

$markAsReadAndRedirect = function ($notificationId, $url) {
    $notification = auth()->user()->notifications()->find($notificationId);
    if ($notification) {
        $notification->markAsRead();
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
    }
    
    if ($url && $url !== '#') {
        return $this->redirect($url, navigate: true);
    }
};

$markAllAsRead = function () {
    if (!auth()->check()) return;
    
    auth()->user()->unreadNotifications->markAsRead();
    $this->unreadCount = 0;
};

// Namun jika Pusher ada, kita bisa menggunakan Echo untuk mendengarkan event Notifikasi bawaan Laravel
on([
    'echo-private:App.Models.User.{authId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => function () {
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
        $this->js("document.getElementById('notif-sound').play().catch(() => {})");
    }
]);

with(fn () => [
    'notifications' => $this->getNotifications()
]);

?>

<div {{ $attributes }}>
    <audio id="notif-sound" src="/notification.mp3" preload="auto"></audio>
    <flux:dropdown position="top" align="start">
        <flux:sidebar.item class="relative cursor-pointer w-full text-start {{ $unreadCount > 0 ? 'bell-has-unread' : '' }}" data-flux-sidebar-action>
            <x-slot:icon>
                <div class="relative">
                    <flux:icon.bell class="size-4 text-zinc-500 dark:text-white/80 group-hover:text-zinc-800 dark:group-hover:text-white" />
                    @if ($unreadCount > 0)
                        <span class="absolute -left-1.5 -top-2 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-red-500 border border-white dark:border-zinc-900 text-[9px] font-bold text-white shadow pointer-events-none z-10">
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                    @endif
                </div>
            </x-slot:icon>
            {{ __('Notifikasi') }}
        </flux:sidebar.item>

        <!-- pop up notifikasi -->
        <flux:menu class="w-80 sm:w-96 p-0 overflow-hidden bg-gray-100">
            {{-- Header --}}
            <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/50">
                <h3 class="font-semibold text-sm text-zinc-800 dark:text-zinc-200">{{ __('Notifikasi') }}</h3>
                @if ($unreadCount > 0)
                    <div class="bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $unreadCount }} Baru</div>
                @endif
            </div>

            {{-- Scrollable Container --}}
            <div class="max-h-[28rem] overflow-y-auto relative p-2 flex flex-col gap-1.5">
                @forelse ($notifications as $notification)
                    <div wire:click="markAsReadAndRedirect('{{ $notification->id }}', '{{ $notification->data['url'] ?? '#' }}')" 
                         wire:target="markAsReadAndRedirect('{{ $notification->id }}', '{{ $notification->data['url'] ?? '#' }}')"
                         wire:loading.class="opacity-50 pointer-events-none"
                         class="cursor-pointer group relative flex items-center gap-2.5 p-2.5 rounded-lg border transition-all duration-200 {{ $notification->read_at ? 'bg-white dark:bg-zinc-900/50 border-zinc-100 dark:border-zinc-800/50 opacity-70 hover:opacity-100 hover:border-zinc-200 dark:hover:border-zinc-700' : 'bg-blue-50/50 dark:bg-blue-900/20 border-blue-100 dark:border-blue-800/50 hover:border-blue-200 dark:hover:border-blue-700 shadow-sm' }}">

                        {{-- Icon Container --}}
                        <div class="shrink-0">
                            <div class="relative h-8 w-8 inline-flex">
                                @if(isset($notification->data['avatar']))
                                    <img src="{{ $notification->data['avatar'] }}" alt="Avatar" class="h-8 w-8 rounded-full object-cover ring-2 ring-white dark:ring-zinc-900 shadow-sm" />
                                    @if(isset($notification->data['icon']))
                                        <div class="absolute -bottom-1 -right-1 h-4 w-4 rounded-full bg-white dark:bg-zinc-800 flex items-center justify-center shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 z-10">
                                            <flux:icon :icon="$notification->data['icon']" class="h-2.5 w-2.5 {{ $notification->data['color'] ?? 'text-zinc-500' }}" />
                                        </div>
                                    @endif
                                @else
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-sm">
                                        @if(isset($notification->data['icon']))
                                            <flux:icon :icon="$notification->data['icon']" class="h-4 w-4 {{ $notification->data['color'] ?? 'text-zinc-500' }}" />
                                        @else
                                            <flux:icon.bell class="h-4 w-4 text-zinc-500" />
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Content Container --}}
                        <div class="flex-1 min-w-0 flex flex-col gap-1">
                            <div class="text-xs leading-snug text-zinc-700 dark:text-zinc-300 break-words">
                                {!! $notification->data['message'] ?? '' !!}
                            </div>
                            
                            {{-- Context Badge --}}
                            @if(isset($notification->data['title']))
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <div class="bg-white dark:bg-zinc-900 text-zinc-500 dark:text-zinc-400 text-[9px] font-semibold px-1.5 py-0.5 rounded flex items-center gap-1 border border-zinc-200 dark:border-zinc-700 shadow-sm">
                                        <flux:icon :icon="$notification->data['icon'] ?? 'bell'" class="w-2.5 h-2.5 {{ $notification->data['color'] ?? 'text-zinc-500' }}" />
                                        {{ $notification->data['title'] }}
                                    </div>
                                </div>
                            @endif

                            <div class="flex items-center justify-between mt-0.5">
                                <p class="text-[10px] text-zinc-400 dark:text-zinc-500 font-medium">
                                    {{ $notification->created_at->diffForHumans() }}
                                </p>
                                
                                @if (!$notification->read_at)
                                    <button wire:click.stop="markAsRead('{{ $notification->id }}')" 
                                            wire:target="markAsRead('{{ $notification->id }}')"
                                            wire:loading.class="opacity-50 pointer-events-none"
                                            class="opacity-0 group-hover:opacity-100 p-0.5 rounded-full hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-all" title="Tandai sudah dibaca">
                                        <flux:icon.check wire:loading.remove wire:target="markAsRead('{{ $notification->id }}')" class="w-3 h-3" />
                                        <svg wire:loading wire:target="markAsRead('{{ $notification->id }}')" class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Unread Indicator Dot --}}
                        @if (!$notification->read_at)
                            <div class="absolute right-2 top-2 w-1.5 h-1.5 bg-blue-500 rounded-full"></div>
                        @endif
                    </div>
                @empty
                    <div class="py-8 text-center flex flex-col items-center justify-center">
                        <div class="w-10 h-10 rounded-full bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-center mb-2">
                            <flux:icon.bell-slash class="h-4 w-4 text-zinc-400 dark:text-zinc-500" />
                        </div>
                        <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Tidak ada notifikasi baru</p>
                    </div>
                @endforelse
            </div>
            
            {{-- Footer --}}
            <div class="border-t border-zinc-200 dark:border-zinc-800 p-3 bg-zinc-50 dark:bg-zinc-800/50 flex justify-between items-center">
                @if ($unreadCount > 0)
                    <button wire:click="markAllAsRead" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors px-1">
                        Tandai semua dibaca
                    </button>
                @else
                    <div></div>
                @endif
                <a href="{{ route('notifications.index') }}" wire:navigate class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200 transition-colors px-1">
                    Lihat semua &rarr;
                </a>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
