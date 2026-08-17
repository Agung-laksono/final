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
    },
    'notifications-updated' => function () {
        if (auth()->check()) {
            $this->unreadCount = auth()->user()->unreadNotifications()->count();
        }
    }
]);

with(fn () => [
    'notifications' => $this->getNotifications()
]);

?>

<div class="flex flex-col-reverse gap-3 items-start min-w-[320px] max-w-[400px]">
    {{-- We iterate in reverse so newest is at the top visually --}}
    @forelse($this->notifications->reverse() as $notification)
        <div wire:key="notif-{{ $notification->id }}" 
             wire:click="markAsReadAndRedirect('{{ $notification->id }}', '{{ $notification->data['url'] ?? '#' }}')" 
             class="flex items-center gap-3 w-full group cursor-pointer transition-transform duration-300 hover:translate-x-2">
            
            <div class="flex items-center justify-center w-10 h-10 shrink-0 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-indigo-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors relative">
                @if(isset($notification->data['icon']))
                    <flux:icon :icon="$notification->data['icon']" class="w-4 h-4 {{ $notification->data['color'] ?? 'text-indigo-500' }}" />
                @else
                    <flux:icon.bell class="w-4 h-4 text-indigo-500" />
                @endif
                
                @if(!$notification->read_at)
                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-zinc-900"></div>
                @endif
            </div>

            <div class="flex flex-col bg-white/95 dark:bg-zinc-800/95 px-4 py-2 rounded-xl shadow-md group-hover:scale-[1.02] transition-transform origin-left w-full border {{ $notification->read_at ? 'border-transparent opacity-80' : 'border-indigo-500/30' }}">
                <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 flex justify-between items-center">
                    {{ $notification->data['title'] ?? 'Notifikasi' }}
                    <span class="text-[9px] text-zinc-500 font-normal">{{ $notification->created_at->diffForHumans() }}</span>
                </span>
                <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-1 line-clamp-2">
                    {!! $notification->data['message'] ?? '' !!}
                </span>
            </div>
            
            @if (!$notification->read_at)
                <button wire:click.stop="markAsRead('{{ $notification->id }}')" 
                        class="opacity-0 group-hover:opacity-100 p-1.5 shrink-0 rounded-full bg-white dark:bg-zinc-800 shadow-md text-zinc-400 hover:text-green-500 transition-all hover:scale-110" title="Tandai sudah dibaca">
                    <flux:icon.check class="w-3.5 h-3.5" />
                </button>
            @endif
        </div>
    @empty
        <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
            <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
            <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Tidak ada notifikasi</span>
        </div>
    @endforelse

    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Notifikasi Terbaru</span>
    </div>

    @if($this->notifications->count() > 0)
    <div class="flex items-center gap-3 w-full mt-2 mb-1">
        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
        <a href="{{ route('notifications.index') }}" wire:navigate class="text-[10px] font-bold text-indigo-500 hover:text-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300 uppercase tracking-widest transition-colors flex items-center gap-1 group">
            Lihat Semua <flux:icon.arrow-right class="w-3 h-3 group-hover:translate-x-1 transition-transform" />
        </a>
    </div>
    @endif
</div>
