<?php

use function Livewire\Volt\{state, on, mount, with};
use Illuminate\Notifications\DatabaseNotification;

state(['unreadCount' => 0, 'authId' => null, 'limit' => 10]);

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
    
    // Ambil notifikasi berdasarkan limit
    return $user->notifications()->take($this->limit)->get();
};

$loadMore = function () {
    $this->limit += 10;
};

$markAsRead = function ($notificationId) {
    if (!auth()->check()) return;
    
    $notification = auth()->user()->notifications()->find($notificationId);
    if ($notification) {
        $notification->markAsRead();
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
        // Sync other browser tabs
        \App\Livewire\Support\NotificationSync::syncToOthers(auth()->id(), $this->unreadCount);
    }
};

$markAsReadAndRedirect = function ($notificationId, $url) {
    $notification = auth()->user()->notifications()->find($notificationId);
    if ($notification) {
        $notification->markAsRead();
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
        // Sync other browser tabs
        \App\Livewire\Support\NotificationSync::syncToOthers(auth()->id(), $this->unreadCount);
    }
    
    if ($url && $url !== '#') {
        return $this->redirect($url, navigate: true);
    }
};

$markAllAsRead = function () {
    if (!auth()->check()) return;
    
    auth()->user()->unreadNotifications->markAsRead();
    $this->unreadCount = 0;
    // Sync other browser tabs
    \App\Livewire\Support\NotificationSync::syncToOthers(auth()->id(), 0);
};

// Realtime update via Laravel Echo (Pusher/Reverb)
on([
    // Notifikasi baru masuk
    'echo-private:App.Models.User.{authId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => function ($event) {
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
        $this->dispatch('$refresh');
        $this->js("document.getElementById('notif-sound') && document.getElementById('notif-sound').play().catch(() => {})");
        
        $title = $event['title'] ?? 'Notifikasi Baru';
        $message = strip_tags($event['message'] ?? 'Anda memiliki notifikasi baru.');
        \Flux::toast(text: $message, heading: $title);
    },
    // Tab lain menandai notifikasi sudah dibaca
    'echo-private:App.Models.User.{authId},.NotificationsRead' => function ($event) {
        $this->unreadCount = $event['unreadCount'];
        $this->dispatch('$refresh');
    },
]);

with(fn () => [
    'notifications' => $this->getNotifications()
]);

?>

<div x-data="{ openNotifs: false, isLoading: false }" class="relative z-50" @click.outside="openNotifs = false" x-init="$watch('openNotifs', value => $dispatch('notifs-toggled', value))">
    <audio id="notif-sound" src="/notification.mp3" preload="auto"></audio>
    {{-- Backdrop (Glassmorphism effect) --}}
    <template x-teleport="body">
        <div x-show="openNotifs" 
             x-transition:enter="transition-opacity ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-white/60 dark:bg-zinc-900/60 backdrop-blur-md z-[45]"
             @click="openNotifs = false"
             style="display: none;">
        </div>
    </template>

    {{-- The Bell Button --}}
    <button @click="openNotifs = !openNotifs" 
            class="relative flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-full shadow-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors text-zinc-600 dark:text-zinc-400 focus:outline-none"
            x-bind:class="openNotifs ? 'bg-indigo-50 dark:bg-indigo-900/30' : ''">
        <flux:icon.bell class="w-4 h-4 transition-transform duration-300" x-bind:class="openNotifs ? 'rotate-12 scale-110 text-indigo-500' : ''" />
        
        @if ($unreadCount > 0)
            <div class="absolute -left-1.5 -top-1 flex h-[16px] min-w-[16px] items-center justify-center rounded-full bg-red-500 border-[2px] border-zinc-50 dark:border-zinc-900 text-[8px] font-extrabold text-white shadow-sm pointer-events-none z-10 leading-none px-1 transition-transform" x-bind:class="openNotifs ? 'scale-110' : ''">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </div>
        @endif
    </button>

    {{-- Single Column Scrollable List --}}
    <div x-show="openNotifs" 
         x-transition:enter="transition ease-out duration-300 transform delay-75"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="absolute left-0 bottom-full mb-4 origin-bottom-left landscape:left-16 landscape:bottom-0 landscape:mb-0 md:left-20 md:bottom-0 md:mb-0 flex flex-col gap-2 items-start pb-2 w-max" style="display: none;">
         
         {{-- Action Header / Utility --}}
         <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
             <div class="w-9 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
             <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">
                 Daftar Notifikasi Terbaru
             </span>
         </div>

         {{-- Scrollable Container --}}
         <div class="flex flex-col gap-1.5 w-[calc(100vw-2rem)] sm:w-[400px] max-h-[calc(100vh-10rem)] landscape:max-h-[calc(100vh-6rem)] md:max-h-[600px] overflow-y-auto px-3 -ml-3 custom-scrollbar pb-4 pt-1">
             @forelse ($notifications as $notification)
                 <div wire:key="notif-{{ $notification->id }}" 
                      wire:click="markAsReadAndRedirect('{{ $notification->id }}', '{{ $notification->data['url'] ?? '#' }}')" 
                      class="flex items-center gap-2.5 w-full min-w-0 group cursor-pointer transition-transform duration-300 hover:translate-x-1 shrink-0">
                     
                     <div class="flex items-center justify-center w-9 h-9 shrink-0 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-indigo-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors relative border border-zinc-100 dark:border-zinc-700">
                         @if(isset($notification->data['icon']))
                             <flux:icon :icon="$notification->data['icon']" class="w-4 h-4 {{ $notification->data['color'] ?? 'text-indigo-500' }}" />
                         @else
                             <flux:icon.bell class="w-4 h-4 text-indigo-500" />
                         @endif
                         
                         @if(!$notification->read_at)
                             <div class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white dark:border-zinc-900 shadow-sm"></div>
                         @endif
                     </div>

                     <div class="flex flex-col bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 px-3 py-1.5 rounded-xl shadow-md group-hover:scale-[1.02] transition-transform origin-left flex-1 min-w-0 {{ $notification->read_at ? 'opacity-70 grayscale-[30%]' : 'shadow-indigo-500/10 border-indigo-500/30' }}">
                         <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 flex justify-between items-center gap-4">
                             <span class="truncate">{{ $notification->data['title'] ?? 'Notifikasi' }}</span>
                             <span class="text-[9px] text-zinc-500 font-normal shrink-0">{{ $notification->created_at->diffForHumans() }}</span>
                         </span>
                         <div class="w-full min-w-0 mt-0.5">
                             <p class="text-[10px] text-zinc-600 dark:text-zinc-400 leading-tight break-words text-wrap">
                                 {!! $notification->data['message'] ?? '' !!}
                             </p>
                         </div>
                     </div>
                     
                     @if (!$notification->read_at)
                         <button wire:click.stop="markAsRead('{{ $notification->id }}')" 
                                 class="opacity-100 md:opacity-0 group-hover:opacity-100 p-1.5 shrink-0 rounded-full bg-white dark:bg-zinc-800 shadow-md border border-zinc-200 dark:border-zinc-700 text-zinc-400 hover:text-green-500 hover:border-green-500/30 transition-all hover:scale-110" title="Tandai sudah dibaca">
                             <flux:icon.check class="w-3.5 h-3.5" />
                         </button>
                     @endif
                 </div>
             @empty
                 <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                     <div class="w-9 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                     <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Tidak ada notifikasi</span>
                 </div>
             @endforelse
             
             {{-- Manual Load More Button --}}
             @if($notifications->count() >= $limit)
             <button wire:click="loadMore" wire:loading.attr="disabled" class="flex items-center justify-center gap-2 w-full mt-2 py-2 text-[10px] font-bold text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200 uppercase tracking-widest bg-zinc-100 dark:bg-zinc-800/50 hover:bg-zinc-200 dark:hover:bg-zinc-700/50 rounded-xl transition-colors outline-none focus:ring-2 focus:ring-indigo-500/50">
                 <span wire:loading.remove wire:target="loadMore">Muat Lebih Banyak</span>
                 <flux:icon.loading wire:loading wire:target="loadMore" class="w-4 h-4 animate-spin text-zinc-500" />
                 <span wire:loading wire:target="loadMore">Memuat...</span>
             </button>
             @endif
         </div>
         
         {{-- Action Row (Footer) --}}
         <div class="flex flex-wrap md:flex-nowrap gap-3 items-center w-[calc(100vw-2rem)] sm:w-[400px] pt-1">
             @if($notifications->count() > 0)
                 <a href="{{ route('notifications.index') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-1">
                     <div class="flex items-center justify-center w-9 h-9 shrink-0 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors border border-zinc-200 dark:border-zinc-700">
                         <flux:icon.arrow-right class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                     </div>
                     <span class="bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 px-3 py-1.5 rounded-xl shadow-md text-[11px] font-bold text-zinc-700 dark:text-zinc-200 group-hover:scale-105 transition-transform origin-left">
                         Lihat Semua
                     </span>
                 </a>
                 
                 @if ($unreadCount > 0)
                 <button wire:click="markAllAsRead" class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-1">
                     <div class="flex items-center justify-center w-9 h-9 shrink-0 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-green-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors border border-zinc-200 dark:border-zinc-700">
                         <flux:icon.check-circle class="w-4 h-4 group-hover:scale-110 transition-transform" />
                     </div>
                     <span class="bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 px-3 py-1.5 rounded-xl shadow-md text-[11px] font-bold text-zinc-700 dark:text-zinc-200 group-hover:scale-105 transition-transform origin-left">
                         Tandai Dibaca
                     </span>
                 </button>
                 @endif
             @endif
         </div>

    </div>
</div>

{{-- Custom Scrollbar Style for the Notifications --}}
<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #475569;
    }
    .custom-scrollbar:hover::-webkit-scrollbar-thumb {
        background: #94a3b8;
    }
    .dark .custom-scrollbar:hover::-webkit-scrollbar-thumb {
        background: #64748b;
    }
</style>
