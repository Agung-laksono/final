<div
    x-data="chatWidget"
    class="fixed bottom-5 right-[88px] z-[9999] flex flex-col items-end gap-3"
    style="position: fixed !important; bottom: 20px !important; right: 88px !important; z-index: 9999 !important; font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;"
>


    {{-- ====================================================== --}}
    {{-- FLOATING BUBBLE BUTTON --}}
    {{-- ====================================================== --}}
    <button
        x-show="!open"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-50"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-50"
        x-transition:enter-end="opacity-100 scale-100"
        @click="$wire.toggleOpen()"
        id="chat-widget-bubble"
        class="relative w-14 h-14 rounded-full shadow-2xl flex items-center justify-center transition-all duration-300 active:scale-90 text-white"
        :class="{
            'bg-gradient-to-br from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500': !animatingSender,
            'animate-pulse ring-4 ring-violet-400': animatingSender
        }"
        title="Chat Internal"
    >
        {{-- Icon: chat (Normal state) --}}
        <span x-show="!animatingSender" x-transition.opacity>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
        </span>
        
        {{-- Avatar Sender (Animating state) --}}
        <template x-if="animatingSender">
            <div class="w-full h-full rounded-full overflow-hidden flex items-center justify-center border-2 border-white shadow-inner bg-gradient-to-br from-emerald-400 to-teal-500 text-white font-bold text-lg">
                <template x-if="animatingSender.avatar">
                    <img :src="animatingSender.avatar" class="w-full h-full object-cover">
                </template>
                <template x-if="!animatingSender.avatar">
                    <span x-text="animatingSender.initials"></span>
                </template>
            </div>
        </template>

        {{-- Unread Badge --}}
        @if($this->totalUnread > 0)
            <span class="absolute -top-1 -right-1 min-w-[20px] h-5 bg-red-500 text-white text-[11px] font-bold rounded-full flex items-center justify-center px-1 ring-2 ring-white dark:ring-zinc-900 shadow-md">
                {{ $this->totalUnread > 99 ? '99+' : $this->totalUnread }}
            </span>
        @endif
    </button>

    {{-- ====================================================== --}}
    {{-- CHAT PANEL --}}
    {{-- ====================================================== --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        x-cloak
        class="w-[360px] max-w-[calc(100vw-24px)] rounded-2xl shadow-2xl overflow-hidden flex flex-col bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/80"
        style="height: 520px; max-height: calc(100vh - 100px);"
    >

        {{-- ============ HEADER PANEL ============ --}}
        <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-violet-600 to-indigo-600 shrink-0">
            <div class="flex items-center gap-2">
                @if($activeConversationId)
                    {{-- Back button --}}
                    <button wire:click="backToList" class="text-white/80 hover:text-white transition-colors mr-1 active:scale-90">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <div class="flex items-center gap-2">
                        {{-- Avatar --}}
                        @php $actConv = $this->activeConversation; @endphp
                        @if($actConv)
                            <div class="relative shrink-0">
                                @if($actConv->type === 'group')
                                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-[16px]">
                                        👥
                                    </div>
                                @elseif($actConv->avatar)
                                    <img src="{{ $actConv->avatar }}" class="w-8 h-8 rounded-full object-cover ring-2 ring-white/50 shadow-sm" alt="">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white font-bold text-sm">
                                        {{ strtoupper(substr($actConv->display, 0, 2)) }}
                                    </div>
                                @endif
                                
                                {{-- Online Indicator --}}
                                @if($actConv->type === 'direct')
                                    <span x-show="isOnline({{ $actConv->other_user_id }})" x-cloak
                                          class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-500 border-[1.5px] border-indigo-600 rounded-full flex items-center justify-center shadow-sm">
                                          <span x-text="getDevice({{ $actConv->other_user_id }})" class="text-[6px] leading-none"></span>
                                    </span>
                                @endif
                            </div>
                            <div>
                                <p class="text-white font-semibold text-sm leading-tight">{{ $actConv->display }}</p>
                                @if($actConv->type === 'group')
                                    <p class="text-white/70 text-[11px]">{{ $actConv->members->count() }} anggota</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                    </svg>
                    <span class="text-white font-semibold text-sm">Chat Internal</span>
                @endif
            </div>

            <div class="flex items-center gap-1.5">
                @unless($activeConversationId)
                    {{-- Buat Grup --}}
                    <button wire:click="$set('showGroupModal', true)" title="Buat Group Chat"
                            class="w-8 h-8 rounded-full flex items-center justify-center text-white/70 hover:text-white hover:bg-white/15 transition-all active:scale-90">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </button>
                @endunless

                {{-- Close Widget Button --}}
                <button @click="$wire.toggleOpen()" title="Tutup Chat"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-white/70 hover:text-white hover:bg-white/15 transition-all active:scale-90 ml-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- ============ CONVERSATION LIST VIEW ============ --}}
        <div x-show="!activeId" class="flex flex-col flex-1 overflow-hidden">

            {{-- Search User --}}
            <div class="px-3 py-2 border-b border-zinc-100 dark:border-zinc-800 shrink-0">
                <div class="flex items-center gap-2 bg-zinc-100 dark:bg-zinc-800 rounded-xl px-3">
                    <svg class="w-4 h-4 text-zinc-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input
                        wire:model.live.debounce.300ms="searchUser"
                        type="text"
                        placeholder="Cari atau mulai chat baru..."
                        class="flex-1 bg-transparent py-2 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 border-none outline-none focus:ring-0"
                    >
                </div>

                {{-- Search Results Dropdown --}}
                @if(strlen($searchUser) >= 2)
                    <div class="mt-1.5 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden bg-white dark:bg-zinc-800 shadow-lg">
                        @forelse($this->searchResults as $user)
                            <button wire:click="openDirectChat({{ $user->id }})"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors text-left">
                                <div class="relative shrink-0">
                                    @if($user->avatarUrl())
                                        <img src="{{ $user->avatarUrl() }}" class="w-8 h-8 rounded-full object-cover ring-2 ring-violet-200 dark:ring-violet-700" alt="">
                                    @else
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-400 to-indigo-500 flex items-center justify-center text-white font-bold text-xs">
                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                        </div>
                                    @endif
                                    {{-- Online Indicator --}}
                                    <span x-show="isOnline({{ $user->id }})" x-cloak
                                          class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-500 border-[1.5px] border-white dark:border-zinc-800 rounded-full flex items-center justify-center shadow-sm">
                                          <span x-text="getDevice({{ $user->id }})" class="text-[6px] leading-none"></span>
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $user->name }}</p>
                                    <p class="text-[11px] text-zinc-500 truncate">{{ $user->email }}</p>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-violet-400 ml-auto shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </button>
                        @empty
                            <p class="text-center text-sm text-zinc-400 py-4">Tidak ada user ditemukan</p>
                        @endforelse
                    </div>
                @endif
            </div>

            {{-- Conversation List --}}
            <div class="flex-1 overflow-y-auto" style="scrollbar-width: thin; scrollbar-color: #d4d4d8 transparent;">
                @forelse($this->conversations as $conv)
                    <button wire:click="selectConversation('{{ $conv->id }}')"
                            class="w-full flex items-center gap-3 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 transition-all border-b border-zinc-50 dark:border-zinc-800/50 text-left active:bg-zinc-100 dark:active:bg-zinc-800">

                        {{-- Avatar --}}
                        <div class="relative shrink-0">
                            @if($conv->type === 'group')
                                {{-- Group: icon khusus --}}
                                <div class="w-11 h-11 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-[22px] shadow-sm">
                                    👥
                                </div>
                            @elseif($conv->avatar)
                                {{-- Direct dengan foto profil --}}
                                <img src="{{ $conv->avatar }}" class="w-11 h-11 rounded-full object-cover ring-2 ring-zinc-100 dark:ring-zinc-700" alt="">
                            @else
                                {{-- Direct tanpa foto → initials --}}
                                <div class="w-11 h-11 rounded-full bg-gradient-to-br from-violet-400 to-indigo-500 flex items-center justify-center text-white font-bold text-base shadow-sm">
                                    {{ strtoupper(substr($conv->display, 0, 2)) }}
                                </div>
                            @endif
                            
                            {{-- Unread dot --}}
                            @if($conv->unread > 0)
                                <span class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-violet-500 rounded-full ring-2 ring-white dark:ring-zinc-900 animate-pulse"></span>
                            @endif

                            {{-- Online Indicator --}}
                            @if($conv->type === 'direct')
                                <span x-show="isOnline({{ $conv->other_user_id }})" x-cloak
                                      class="absolute bottom-0 right-0 w-4 h-4 bg-emerald-500 border-2 border-white dark:border-zinc-900 rounded-full flex items-center justify-center shadow-sm">
                                      <span x-text="getDevice({{ $conv->other_user_id }})" class="text-[8px] leading-none"></span>
                                </span>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-baseline mb-0.5">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $conv->display }}</p>
                                <span class="text-[11px] {{ $conv->unread > 0 ? 'text-violet-500 font-semibold' : 'text-zinc-400' }} shrink-0 ml-1">
                                    {{ $conv->last_message_at ? $conv->last_message_at->format('H:i') : '' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <p class="text-[12.5px] text-zinc-500 dark:text-zinc-400 truncate flex-1 pr-2">
                                    @if($conv->latestMessage)
                                        @if($conv->latestMessage->sender_id === auth()->id())
                                            <span class="text-zinc-400">Anda: </span>
                                        @elseif($conv->type === 'group')
                                            <span class="text-zinc-400">{{ $conv->latestMessage->sender?->name }}: </span>
                                        @endif
                                        {{ Str::limit($conv->latestMessage->body, 35) }}
                                    @else
                                        <span class="italic text-zinc-400">Belum ada pesan</span>
                                    @endif
                                </p>
                                @if($conv->unread > 0)
                                    <span class="bg-violet-500 text-white text-[11px] font-bold min-w-[20px] h-5 rounded-full flex items-center justify-center px-1 shrink-0">
                                        {{ $conv->unread > 99 ? '99+' : $conv->unread }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="flex flex-col items-center justify-center h-full py-12 text-zinc-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="text-sm font-medium">Belum ada percakapan</p>
                        <p class="text-xs mt-1">Cari rekan kerja di atas untuk memulai</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ============ CHAT ROOM VIEW ============ --}}
        <div x-show="activeId" x-cloak class="flex flex-col flex-1 overflow-hidden">

            {{-- Loading overlay --}}
            <div wire:loading wire:target="selectConversation,openDirectChat"
                 class="absolute inset-0 z-50 bg-white/80 dark:bg-zinc-900/80 flex items-center justify-center backdrop-blur-sm">
                <svg class="animate-spin w-8 h-8 text-violet-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            {{-- Messages area --}}
            <div
                id="chat-widget-messages"
                class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 bg-zinc-50 dark:bg-zinc-900/50 relative"
                style="scrollbar-width: thin; scrollbar-color: #d4d4d8 transparent;"
                @scroll.debounce.150ms="
                    if ($el.scrollTop < 50) {
                        $wire.loadMore();
                    }
                "
                x-init="
                    $wire.on('chat-scroll-bottom', () => {
                        setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 80);
                    });
                    setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 150);
                "
            >
                {{-- Loading Indicator for Older Messages --}}
                <div wire:loading wire:target="loadMore" class="w-full flex justify-center py-2 absolute top-0 left-0 z-10">
                    <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-full p-1.5 flex items-center justify-center">
                        <svg class="animate-spin h-4 w-4 text-violet-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>

                @php $lastDate = null; @endphp
                @forelse($this->messages as $msg)
                    @php 
                        $isMe = $msg->sender_id === auth()->id(); 
                        $currentDate = $msg->created_at->format('Y-m-d');
                    @endphp
                    
                    {{-- Date Separator --}}
                    @if($lastDate !== $currentDate)
                        <div class="flex justify-center my-4">
                            <span class="bg-zinc-200/50 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 text-[11px] font-medium px-3 py-1 rounded-full shadow-sm backdrop-blur-sm">
                                @if($msg->created_at->isToday())
                                    Hari ini
                                @elseif($msg->created_at->isYesterday())
                                    Kemarin
                                @elseif($msg->created_at->isCurrentYear())
                                    {{ $msg->created_at->translatedFormat('d M') }}
                                @else
                                    {{ $msg->created_at->translatedFormat('d M Y') }}
                                @endif
                            </span>
                        </div>
                        @php $lastDate = $currentDate; @endphp
                    @endif

                    <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }} items-end gap-1.5 group">

                        {{-- Other user avatar (foto profil jika ada, fallback initials) --}}
                        @unless($isMe)
                            @php $senderAvatar = $msg->sender?->avatarUrl(); @endphp
                            @if($senderAvatar)
                                <img src="{{ $senderAvatar }}" class="w-6 h-6 rounded-full object-cover shrink-0 mb-0.5 ring-1 ring-zinc-200 dark:ring-zinc-600" alt="">
                            @else
                                <div class="w-6 h-6 rounded-full bg-gradient-to-br from-violet-400 to-indigo-500 flex items-center justify-center text-white font-bold text-[10px] shrink-0 mb-0.5">
                                    {{ strtoupper(substr($msg->sender?->name ?? 'U', 0, 2)) }}
                                </div>
                            @endif
                        @endunless

                        <div class="max-w-[75%]">
                            {{-- Group: tampilkan nama sender --}}
                            @if(!$isMe && $this->activeConversation?->type === 'group')
                                <p class="text-[10px] text-violet-500 font-semibold mb-0.5 px-1">{{ $msg->sender?->name }}</p>
                            @endif

                            {{-- Bubble --}}
                            <div class="relative px-3 py-2 rounded-2xl shadow-sm text-sm leading-relaxed
                                {{ $isMe
                                    ? 'bg-gradient-to-br from-violet-500 to-indigo-600 text-white rounded-br-sm'
                                    : 'bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 rounded-bl-sm border border-zinc-100 dark:border-zinc-700' }}">
                                
                                @if($msg->type === 'image' && $msg->attachment_url)
                                    <div class="mb-2 -mx-1 -mt-1">
                                        <img src="{{ asset($msg->attachment_url) }}" class="rounded-xl max-w-full h-auto" style="max-height: 200px; cursor: zoom-in;" 
                                             @click.stop="$dispatch('open-lightbox', { url: '{{ asset($msg->attachment_url) }}' })">
                                    </div>
                                @endif

                                @if($msg->body)
                                    <p class="whitespace-pre-wrap break-words pr-10">{{ $msg->body }}</p>
                                @else
                                    <div class="pr-10"></div>
                                @endif

                                <div class="absolute bottom-1.5 right-2 flex items-center gap-0.5 text-[10px] {{ $isMe ? 'text-white/70' : 'text-zinc-400' }} whitespace-nowrap">
                                    <span>{{ $msg->created_at->format('H:i') }}</span>
                                    @if($isMe)
                                        @php
                                            $isRead = $this->otherLastReadAt && $msg->created_at <= $this->otherLastReadAt;
                                        @endphp
                                        @if($isRead)
                                            {{-- Double tick (Read) --}}
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-cyan-300">
                                              <path d="M18 6 7 17l-5-5"/>
                                              <path d="m22 10-7.5 7.5L13 16"/>
                                            </svg>
                                        @else
                                            {{-- Single tick (Sent/Unread) --}}
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 opacity-70">
                                              <path d="M20 6 9 17l-5-5"/>
                                            </svg>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center h-full text-zinc-400 py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        <p class="text-sm">Kirim pesan pertama! 👋</p>
                    </div>
                @endforelse
            </div>

            {{-- Typing Indicator Bubble (Fixed position above input) --}}
            <div x-show="remoteTypingUser" x-transition.opacity.duration.300ms style="display: none;" class="w-full bg-zinc-50 dark:bg-zinc-900 px-3 pb-2 pt-1">
                <div class="flex justify-start items-end gap-1.5 group">
                    {{-- Initials Placeholder --}}
                    <div class="w-6 h-6 rounded-full bg-gradient-to-br from-zinc-300 to-zinc-400 dark:from-zinc-600 dark:to-zinc-700 flex items-center justify-center text-white font-bold text-[10px] shrink-0 mb-0.5">
                        <span x-text="(remoteTypingUser || 'U').substring(0, 2).toUpperCase()"></span>
                    </div>
                    
                    <div class="max-w-[75%]">
                        <div class="relative px-3 py-2 rounded-2xl shadow-sm text-sm leading-relaxed bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 rounded-bl-sm border border-zinc-100 dark:border-zinc-700 flex items-center gap-2">
                            <span class="text-[11px] text-zinc-500 italic">sedang mengetik</span>
                            <span class="flex gap-0.5 mt-1">
                                <span class="w-1 h-1 bg-zinc-400 rounded-full animate-bounce"></span>
                                <span class="w-1 h-1 bg-zinc-400 rounded-full animate-bounce" style="animation-delay: 150ms;"></span>
                                <span class="w-1 h-1 bg-zinc-400 rounded-full animate-bounce" style="animation-delay: 300ms;"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Input Bar --}}
            <div class="px-3 py-2.5 border-t border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shrink-0">
                
                {{-- Preview Attachment(s) --}}
                @if(count($attachments) > 0)
                    <div class="mb-2 flex flex-wrap gap-2 px-1">
                        @foreach($attachments as $index => $att)
                            <div class="relative inline-block">
                                <img src="{{ is_string($att) ? $att : '' }}" class="h-16 rounded-lg object-cover ring-2 ring-violet-500/50">
                                <button type="button" wire:click="removeAttachment({{ $index }})" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center shadow hover:scale-110 transition-transform z-10">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form wire:submit.prevent="sendMessage"
                      class="flex items-end gap-2"
                      x-data="{ hasText: false }"
                      @chat-message-sent.window="hasText = false"
                >
                    <div class="flex-1 bg-zinc-100 dark:bg-zinc-800 rounded-2xl px-2 py-1 flex items-end gap-1">
                        
                        {{-- Image Cropper Attachment --}}
                        <div class="shrink-0 mb-0.5">
                            <x-image-cropper wire:model="attachment" mode="icon" label="" id="chat-attach" accept="image/*" />
                        </div>

                        <textarea
                            id="chat-widget-input"
                            wire:model="messageInput"
                            wire:loading.attr="readonly"
                            wire:target="sendMessage"
                            wire:loading.class="opacity-50"
                            rows="1"
                            placeholder="Tulis pesan..."
                            class="flex-1 bg-transparent border-none outline-none focus:ring-0 resize-none text-[13.5px] text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 leading-relaxed py-2 transition-opacity"
                            style="max-height: 96px; scrollbar-width: none;"
                            x-init="
                                $el.addEventListener('input', function() {
                                    hasText = this.value.length > 0;
                                    this.style.height = 'auto';
                                    this.style.height = Math.min(this.scrollHeight, 96) + 'px';

                                    // Trigger whisper (typing) dengan teknik Debounce (Start/Stop)
                                    if ($wire.activeConversationId && window.Echo) {
                                        // Jika belum berstatus mengetik, kirim sinyal START
                                        if (!isLocalTyping) {
                                            isLocalTyping = true;
                                            window.Echo.private('chat.' + $wire.activeConversationId)
                                                .whisper('typing', { name: '{{ auth()->user()->name ?? 'User' }}' });
                                        }
                                        
                                        // Reset timer setiap kali tombol ditekan
                                        if (localTypingTimeout) clearTimeout(localTypingTimeout);
                                        
                                        // Jika diam 5 detik, kirim sinyal STOP
                                        localTypingTimeout = setTimeout(() => {
                                            if (isLocalTyping) {
                                                isLocalTyping = false;
                                                window.Echo.private('chat.' + $wire.activeConversationId)
                                                    .whisper('stop-typing');
                                            }
                                        }, 5000);
                                    }
                                });
                            "
                            @blur="
                                // Jika form tidak aktif (blur), matikan indikator
                                if (isLocalTyping && $wire.activeConversationId && window.Echo) {
                                    isLocalTyping = false;
                                    if (localTypingTimeout) clearTimeout(localTypingTimeout);
                                    window.Echo.private('chat.' + $wire.activeConversationId).whisper('stop-typing');
                                }
                            "
                            @keydown.enter="
                                // Deteksi perangkat mobile/sentuh
                                let isMobile = window.innerWidth <= 768 || ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
                                
                                // Di HP, tombol Enter/Return keyboard selalu membuat baris baru (default).
                                // Abaikan logika pengiriman agar user bisa mengetik baris baru dengan mudah.
                                if (isMobile) return;

                                // Logika khusus Desktop
                                if (!$event.shiftKey) {
                                    $event.preventDefault(); // Mencegah Enter bawaan (newline) hanya jika bukan Shift+Enter
                                    if ($el.value.trim() || $wire.attachments.length > 0) {
                                        // Matikan typing indicator seketika saat pesan dikirim
                                        if (isLocalTyping && $wire.activeConversationId && window.Echo) {
                                            isLocalTyping = false;
                                            if (localTypingTimeout) clearTimeout(localTypingTimeout);
                                            window.Echo.private('chat.' + $wire.activeConversationId).whisper('stop-typing');
                                        }
                                        
                                        $wire.sendMessage();
                                        $el.style.height = 'auto';
                                        hasText = false;
                                    }
                                }
                                // Jika Shift+Enter di Desktop, biarkan perilaku bawaan berjalan (menambah baris baru di textarea)
                            "
                        ></textarea>
                    </div>

                    {{-- Send Button --}}
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 transition-all duration-200 active:scale-90
                                   bg-gradient-to-br from-violet-500 to-indigo-600 hover:from-violet-400 hover:to-indigo-500 text-white shadow-md relative"
                            :class="(hasText || $wire.attachments.length > 0) ? 'opacity-100 scale-100' : 'opacity-60 scale-95 cursor-default'"
                    >
                        {{-- Icon Send --}}
                        <svg wire:loading.remove wire:target="sendMessage" viewBox="0 0 24 24" class="w-[18px] h-[18px] translate-x-0.5" fill="currentColor">
                            <path d="M1.101 21.757L23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"/>
                        </svg>

                        {{-- Icon Loading Spinner --}}
                        <svg wire:loading wire:target="sendMessage" class="animate-spin w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </form>
                <p class="text-[10px] text-zinc-400 mt-1 px-1">Enter kirim · Shift+Enter baris baru</p>
            </div>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- GROUP CREATION MODAL --}}
    {{-- ====================================================== --}}
    <flux:modal wire:model="showGroupModal" class="md:w-[440px]">
        <div class="space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Buat Group Chat</h2>
                <p class="text-sm text-zinc-500 mt-0.5">Pilih anggota dan berikan nama untuk group.</p>
            </div>

            <flux:input wire:model="groupName" label="Nama Group" placeholder="Contoh: Tim Sales, Gudang Jakarta..." />

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Pilih Anggota</label>
                <div class="space-y-1 max-h-52 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-700 p-1.5 bg-zinc-50 dark:bg-zinc-800/50" style="scrollbar-width: thin;">
                    @foreach($this->allUsers as $user)
                        <button type="button"
                                wire:click="toggleGroupMember({{ $user->id }})"
                                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white dark:hover:bg-zinc-700 transition-colors text-left
                                       {{ in_array($user->id, $selectedGroupMembers) ? 'bg-violet-50 dark:bg-violet-900/30 ring-1 ring-violet-300 dark:ring-violet-700' : '' }}">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-400 to-indigo-500 flex items-center justify-center text-white font-bold text-xs shrink-0">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $user->name }}</p>
                                <p class="text-xs text-zinc-500 truncate">{{ $user->email }}</p>
                            </div>
                            @if(in_array($user->id, $selectedGroupMembers))
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-violet-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </button>
                    @endforeach
                </div>
                @if(count($selectedGroupMembers) > 0)
                    <p class="text-xs text-violet-500 font-medium mt-1.5">{{ count($selectedGroupMembers) }} anggota dipilih (+ Anda)</p>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('showGroupModal', false)" variant="ghost">Batal</flux:button>
                <flux:button wire:click="createGroup" variant="primary" icon="user-group">Buat Group</flux:button>
            </div>
        </div>
    </flux:modal>

    @script
    <script>
        Alpine.data('chatWidget', () => ({
                open: @entangle('isOpen').live,
                activeId: @entangle('activeConversationId').live,
                currentChannel: null,
                globalChannel: null,
                authUserId: {{ auth()->id() ?? 'null' }},
                onlineUsers: {},
                animatingSender: null,
                animationTimeout: null,
                
                // Typing Indicator State
                isLocalTyping: false,
                localTypingTimeout: null,
                remoteTypingUser: null,
                remoteTypingTimeout: null,

                isOnline(userId) {
                    return this.onlineUsers && this.onlineUsers[userId] !== undefined;
                },

                getDevice(userId) {
                    if (!this.isOnline(userId)) return '';
                    return this.onlineUsers[userId] === 'mobile' ? '📱' : '💻';
                },

                /**
                 * Putar suara chat masuk.
                 * Menggunakan fungsi global dari head.blade.php
                 */
                playChatSound() {
                    if (window.playNotificationSound) {
                        window.playNotificationSound();
                    }
                },

                /**
                 * Memicu animasi "kembang kempis" pada icon chat dengan menampilkan avatar pengirim.
                 */
                triggerAnimation(event) {
                    // Jangan animate jika widget sedang terbuka, atau jika pesan dari diri sendiri
                    if (this.open || event.sender_id == this.authUserId) return;

                    this.animatingSender = {
                        name: event.sender_name || 'U',
                        avatar: event.sender_avatar || null,
                        initials: (event.sender_name || 'U').substring(0, 2).toUpperCase(),
                    };

                    if (this.animationTimeout) clearTimeout(this.animationTimeout);

                    // Hilangkan animasi dan kembalikan icon normal setelah 4 detik
                    this.animationTimeout = setTimeout(() => {
                        this.animatingSender = null;
                    }, 4000);
                },

                /**
                 * Subscribe ke private Pusher channel untuk conversation aktif.
                 * Unsubscribe dari channel sebelumnya secara otomatis.
                 */
                subscribeToConversation(conversationId) {
                    // Lepas subscription lama
                    if (this.currentChannel && window.Echo) {
                        window.Echo.leave('chat.' + this.currentChannel);
                        this.currentChannel = null;
                    }

                    if (!conversationId || !window.Echo) return;

                    this.currentChannel = conversationId;

                    window.Echo.private('chat.' + conversationId)
                        .listen('.MessageSent', (event) => {
                            // Kirim ke Livewire → handleNewMessage() → refreshKey++ → re-render
                            this.$wire.handleNewMessage(event);

                            // Putar suara hanya jika bukan pesan sendiri
                            if (event.sender_id != this.authUserId) {
                                this.playChatSound();
                                this.triggerAnimation(event);
                            }
                        })
                        .listen('.ConversationRead', (event) => {
                            if (event.userId != this.authUserId) {
                                this.$wire.$refresh();
                            }
                        })
                        .listenForWhisper('typing', (e) => {
                            this.remoteTypingUser = e.name;
                            
                            // Safety timeout: jika tidak ada stop-typing setelah 15 detik, hilangkan otomatis
                            if (this.remoteTypingTimeout) clearTimeout(this.remoteTypingTimeout);
                            this.remoteTypingTimeout = setTimeout(() => {
                                this.remoteTypingUser = null;
                            }, 15000);
                        })
                        .listenForWhisper('stop-typing', (e) => {
                            this.remoteTypingUser = null;
                            if (this.remoteTypingTimeout) clearTimeout(this.remoteTypingTimeout);
                        });
                },

                /**
                 * Subscribe ke public global channel untuk update badge unread
                 * saat ada pesan di conversation lain yang sedang tidak terbuka.
                 */
                subscribeGlobal() {
                    if (!window.Echo) return;

                    this.globalChannel = window.Echo.channel('chat-global')
                        .listen('.MessageSent', (event) => {
                            // Hanya refresh conversation list jika bukan conversation aktif
                            if (event.conversation_id !== this.activeId) {
                                this.$wire.refreshConversations();

                                // Suara untuk pesan di conversation lain (tidak sedang dibuka)
                                if (event.sender_id != this.authUserId) {
                                    this.playChatSound();
                                    this.triggerAnimation(event);
                                }
                            }
                        });
                },

                init() {
                    // Watch perubahan activeId → re-subscribe ke channel baru
                    this.$watch('activeId', (newId) => {
                        this.subscribeToConversation(newId);
                    });

                    // Subscribe ke global channel untuk badge dan presence channel untuk status online
                    this.$nextTick(() => {
                        this.subscribeGlobal();
                        this.subscribePresence();

                        // Subscribe ke conversation awal jika ada
                        if (this.activeId) {
                            this.subscribeToConversation(this.activeId);
                        }
                    });
                },

                /**
                 * Subscribe ke presence channel untuk melacak siapa saja yang sedang online
                 */
                subscribePresence() {
                    if (!window.Echo) return;

                    window.Echo.join('chat-presence')
                        .here((users) => {
                            this.onlineUsers = {};
                            users.forEach(u => this.onlineUsers[u.id] = u.device || 'desktop');
                        })
                        .joining((user) => {
                            this.onlineUsers[user.id] = user.device || 'desktop';
                        })
                        .leaving((user) => {
                            delete this.onlineUsers[user.id];
                        });
                }
        }));
    </script>
    @endscript
</div>
