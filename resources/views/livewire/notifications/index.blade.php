<?php

use function Livewire\Volt\{state, on, with, layout};
use Illuminate\Notifications\DatabaseNotification;

layout('layouts.app');

with(fn () => [
    'notifications' => auth()->user()->notifications()->paginate(15)
]);

$markAsRead = function ($notificationId) {
    $notification = auth()->user()->notifications()->find($notificationId);
    if ($notification) {
        $notification->markAsRead();
        $this->dispatch('notifications-updated');
        \Flux::toast('Notifikasi ditandai sudah dibaca.');
    }
};

$markAllAsRead = function () {
    auth()->user()->unreadNotifications->markAsRead();
    $this->dispatch('notifications-updated');
    \Flux::toast('Semua notifikasi ditandai sudah dibaca.');
};

$delete = function ($notificationId) {
    $notification = auth()->user()->notifications()->find($notificationId);
    if ($notification) {
        $notification->delete();
        $this->dispatch('notifications-updated');
        \Flux::toast('Notifikasi berhasil dihapus.');
    }
};

$deleteAll = function () {
    auth()->user()->notifications()->delete();
    $this->dispatch('notifications-updated');
    \Flux::toast('Semua notifikasi berhasil dihapus.');
};

?>

<div class="max-w-4xl mx-auto py-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
        <div>
            <flux:heading size="xl">Notifikasi</flux:heading>
            <flux:subheading>Daftar pemberitahuan aktivitas dalam sistem.</flux:subheading>
        </div>
        
        <div class="flex gap-2">
            @if(auth()->user()->unreadNotifications()->count() > 0)
                <flux:button wire:click="markAllAsRead" variant="subtle" icon="check-circle" size="sm">
                    Tandai Semua Dibaca
                </flux:button>
            @endif
            @if(auth()->user()->notifications()->count() > 0)
                <flux:button wire:click="deleteAll" wire:confirm="Hapus seluruh riwayat notifikasi?" variant="danger" icon="trash" size="sm">
                    Bersihkan Semua
                </flux:button>
            @endif
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden">
        @if($notifications->count() > 0)
            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($notifications as $notification)
                    <div class="group relative flex items-center gap-4 p-5 transition-all duration-200 {{ $notification->read_at ? 'bg-transparent opacity-50 hover:opacity-80 grayscale-[50%]' : 'bg-blue-50/80 dark:bg-blue-900/20 hover:bg-blue-100/80 dark:hover:bg-blue-900/30' }}">
                        
                        {{-- Icon / Avatar --}}
                        <div class="shrink-0">
                            <div class="relative h-10 w-10 inline-flex">
                                @if(isset($notification->data['avatar']))
                                    <img src="{{ $notification->data['avatar'] }}" alt="Avatar" class="h-10 w-10 rounded-full object-cover ring-2 ring-white dark:ring-zinc-900 shadow-sm" />
                                    @if(isset($notification->data['icon']))
                                        <div class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full bg-white dark:bg-zinc-800 flex items-center justify-center shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 z-10">
                                            <flux:icon :icon="$notification->data['icon']" class="h-3 w-3 {{ $notification->data['color'] ?? 'text-zinc-500' }}" />
                                        </div>
                                    @endif
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white dark:bg-zinc-800 ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-sm">
                                        @if(isset($notification->data['icon']))
                                            <flux:icon :icon="$notification->data['icon']" class="h-5 w-5 {{ $notification->data['color'] ?? 'text-zinc-500' }}" />
                                        @else
                                            <flux:icon.bell class="h-5 w-5 text-zinc-500" />
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start gap-4">
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-1">
                                        {{ $notification->data['title'] ?? 'Pemberitahuan Sistem' }}
                                    </p>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 break-words leading-relaxed">
                                        {!! $notification->data['message'] ?? '' !!}
                                    </p>
                                </div>
                                <span class="shrink-0 text-xs text-zinc-400 font-medium bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded-md">
                                    {{ $notification->created_at->diffForHumans() }}
                                </span>
                            </div>
                            
                            {{-- Action Links --}}
                            @if(isset($notification->data['url']))
                                <div class="mt-3">
                                    <flux:button href="{{ $notification->data['url'] }}" variant="ghost" size="sm" class="text-blue-600 dark:text-blue-400 -ml-3">
                                        Lihat Detail &rarr;
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                        
                        {{-- Floating Actions on Hover --}}
                        <div class="absolute right-4 bottom-4 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            @if (!$notification->read_at)
                                <flux:button wire:click="markAsRead('{{ $notification->id }}')" variant="ghost" size="sm" icon="check" class="text-zinc-500" title="Tandai dibaca" />
                            @endif
                            <flux:button wire:click="delete('{{ $notification->id }}')" wire:confirm="Hapus notifikasi ini?" variant="ghost" size="sm" icon="trash" class="text-rose-500" title="Hapus" />
                        </div>
                        
                        {{-- Unread Indicator --}}
                        @if (!$notification->read_at)
                            <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-12 bg-blue-500 rounded-r"></div>
                        @endif
                    </div>
                @endforeach
            </div>
            
            @if($notifications->hasPages())
                <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/50">
                    {{ $notifications->links() }}
                </div>
            @endif
        @else
            <div class="py-16 px-6 text-center flex flex-col items-center">
                <div class="h-16 w-16 bg-zinc-100 dark:bg-zinc-800 rounded-full flex items-center justify-center mb-4">
                    <flux:icon.bell-slash class="h-8 w-8 text-zinc-400 dark:text-zinc-500" />
                </div>
                <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-1">Kosong</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 max-w-sm mx-auto">
                    Anda tidak memiliki notifikasi saat ini. Pemberitahuan aktivitas baru akan muncul di sini.
                </p>
            </div>
        @endif
    </div>
</div>
