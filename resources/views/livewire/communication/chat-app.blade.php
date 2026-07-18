<div class="flex flex-col md:flex-row fixed top-0 left-0 w-full h-[100dvh] z-[100] md:relative md:z-auto md:inset-auto md:w-full md:h-[calc(100vh-80px)] overflow-hidden md:rounded-xl md:shadow-lg md:border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#111b21]" style="font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;">

    {{-- ============================================================ --}}
    {{-- LEFT PANEL: Chat List (WhatsApp Sidebar Style) --}}
    {{-- ============================================================ --}}
    <div class="{{ $activeConversationId ? 'hidden md:flex' : 'flex' }} w-full md:w-[360px] md:min-w-[300px] flex-col bg-white dark:bg-[#111b21] border-r border-zinc-200 dark:border-[#2a3942]">

        {{-- Sidebar Header --}}
        <div class="flex items-center justify-between px-4 py-3 bg-[#f0f2f5] dark:bg-[#202c33] shrink-0">
            <div class="flex items-center gap-3">
                {{-- Current User Avatar --}}
                <div class="w-10 h-10 rounded-full bg-emerald-500 flex items-center justify-center text-white font-bold text-sm shrink-0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 2)) }}
                </div>
            </div>
            <div class="flex items-center gap-2">
                {{-- New Chat Button --}}
                <button wire:click="$set('showNewChatModal', true)" 
                        title="Chat Baru"
                        class="w-10 h-10 rounded-full flex items-center justify-center text-[#54656f] dark:text-[#aebac1] hover:bg-black/10 dark:hover:bg-white/10 transition-all duration-200 active:scale-90">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor">
                        <path d="M19.005 3.175H4.674C3.642 3.175 3 3.789 3 4.821V21.02l3.544-3.514h12.461c1.033 0 2.064-1.06 2.064-2.093V4.821c-.001-1.032-1.032-1.646-2.064-1.646zm-4.989 9.869H7.041V11.1h6.975v1.944zm3-4H7.041V7.1h9.975v1.944z"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Search Bar --}}
        <div class="px-3 py-2 bg-white dark:bg-[#111b21] shrink-0">
            <div class="flex items-center bg-[#f0f2f5] dark:bg-[#202c33] rounded-lg px-3 gap-2">
                <svg class="w-4 h-4 text-[#54656f] dark:text-[#aebac1] shrink-0" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M15.009 13.805h-.636l-.22-.219a5.184 5.184 0 0 0 1.256-3.386 5.207 5.207 0 1 0-5.207 5.208 5.183 5.183 0 0 0 3.385-1.255l.221.22v.635l4.004 3.999 1.194-1.195-3.997-4.007zm-4.808 0a3.605 3.605 0 1 1 0-7.21 3.605 3.605 0 0 1 0 7.21z"/>
                </svg>
                <input type="text" placeholder="Cari atau mulai percakapan baru" 
                       class="flex-1 bg-transparent text-sm py-2 text-zinc-800 dark:text-[#d1d7db] placeholder-[#54656f] dark:placeholder-[#8696a0] border-none outline-none focus:ring-0">
            </div>
        </div>

        {{-- Conversation List --}}
        <div class="flex-1 overflow-y-auto" style="scrollbar-width: thin; scrollbar-color: #ccc transparent;">
            @forelse($conversations as $conversation)
                @php
                    $lastMsg = $conversation->latestMessage;
                    $isActive = $activeConversationId === $conversation->id;
                @endphp
                <div wire:click="selectConversation('{{ $conversation->id }}')"
                     class="flex items-center gap-3 px-4 py-3 cursor-pointer border-b border-zinc-100 dark:border-[#2a3942]/50 hover:bg-[#f5f6f6] dark:hover:bg-[#2a3942] transition-all duration-200 active:scale-[0.98] active:bg-[#e9edef] relative
                            {{ $isActive ? 'bg-[#f0f2f5] dark:bg-[#2a3942]' : 'bg-white dark:bg-[#111b21]' }}">
                    
                    {{-- Avatar --}}
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white font-semibold text-base shrink-0">
                        {{ strtoupper(substr($conversation->name ?? $conversation->phone_number, 0, 2)) }}
                    </div>
                    
                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-baseline">
                            <h4 class="font-semibold text-zinc-900 dark:text-[#e9edef] truncate text-[15px]">
                                {{ $conversation->name ?? $conversation->phone_number }}
                            </h4>
                            <span class="text-[11px] shrink-0 ml-1 {{ $conversation->unread_count > 0 ? 'text-[#25d366]' : 'text-[#667781] dark:text-[#8696a0]' }}">
                                {{ $conversation->last_message_at ? $conversation->last_message_at->format('H:i') : '' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center mt-0.5">
                            <p class="text-[13px] text-[#667781] dark:text-[#8696a0] truncate flex-1 pr-2">
                                @if($lastMsg)
                                    @if($lastMsg->direction === 'out')
                                        <span class="inline-flex items-center gap-0.5 shrink-0">
                                            @if($lastMsg->status === 'read')
                                                {{-- Double tick BLUE (read) --}}
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" class="w-4 h-4 text-[#53bdeb] shrink-0" fill="currentColor">
                                                    <path d="M17.394 5.035l-.57-.444a.434.434 0 0 0-.609.076L8.397 14.019l-3.853-3.461a.434.434 0 0 0-.608.015l-.494.509a.434.434 0 0 0 .014.609l4.561 4.095a.434.434 0 0 0 .606-.039l8.785-10.127a.434.434 0 0 0-.014-.585z"/>
                                                    <path d="M13.394 5.035l-.57-.444a.434.434 0 0 0-.609.076L4.397 14.019l-.853-.766-.608.015-.494.509a.434.434 0 0 0 .014.609l1.561 1.402a.434.434 0 0 0 .606-.039l8.785-10.127a.434.434 0 0 0-.014-.588z" opacity=".5"/>
                                                </svg>
                                            @elseif($lastMsg->status === 'delivered')
                                                {{-- Double tick GREY (delivered) --}}
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" class="w-4 h-4 text-[#667781] shrink-0" fill="currentColor">
                                                    <path d="M17.394 5.035l-.57-.444a.434.434 0 0 0-.609.076L8.397 14.019l-3.853-3.461a.434.434 0 0 0-.608.015l-.494.509a.434.434 0 0 0 .014.609l4.561 4.095a.434.434 0 0 0 .606-.039l8.785-10.127a.434.434 0 0 0-.014-.585z"/>
                                                    <path d="M13.394 5.035l-.57-.444a.434.434 0 0 0-.609.076L4.397 14.019l-.853-.766-.608.015-.494.509a.434.434 0 0 0 .014.609l1.561 1.402a.434.434 0 0 0 .606-.039l8.785-10.127a.434.434 0 0 0-.014-.588z" opacity=".5"/>
                                                </svg>
                                            @elseif($lastMsg->status === 'sent')
                                                {{-- Single tick grey (sent) --}}
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" class="w-4 h-4 text-[#667781] shrink-0" fill="currentColor">
                                                    <path d="M17.394 5.035l-.57-.444a.434.434 0 0 0-.609.076L8.397 14.019l-3.853-3.461a.434.434 0 0 0-.608.015l-.494.509a.434.434 0 0 0 .014.609l4.561 4.095a.434.434 0 0 0 .606-.039l8.785-10.127a.434.434 0 0 0-.014-.585z"/>
                                                </svg>
                                            @endif
                                        </span>
                                    @endif
                                    {{ Str::limit($lastMsg->message, 38) }}
                                @else
                                    Mulai percakapan
                                @endif
                            </p>
                            @if($conversation->unread_count > 0)
                                <span class="bg-[#25d366] text-white text-[11px] font-semibold px-1.5 py-0.5 rounded-full min-w-[20px] text-center shrink-0">
                                    {{ $conversation->unread_count }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-16 text-[#667781] dark:text-[#8696a0]">
                    <svg class="w-16 h-16 mb-4 opacity-30" fill="currentColor" viewBox="0 0 303.083 303.083">
                        <path d="M237.525 39.783C211.616 14.283 177.244 0 140.682 0 63.526 0 .841 62.684.841 139.841c0 24.641 6.566 48.67 19.049 69.72L0 303.083l95.295-24.984c20.287 11.063 43.148 16.897 66.372 16.897h.057c77.108 0 139.366-62.683 139.359-139.841-.001-37.336-14.552-72.437-63.558-115.372z"/>
                    </svg>
                    <p class="text-sm">Belum ada percakapan</p>
                    <p class="text-xs mt-1">Klik ikon chat di atas untuk memulai</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- RIGHT PANEL: Chat Room (WhatsApp Chat Window Style) --}}
    {{-- ============================================================ --}}
    <div class="{{ $activeConversationId ? 'flex' : 'hidden md:flex' }} flex-1 flex-col relative overflow-hidden w-full bg-[#f0f2f5] dark:bg-[#222e35]">
        
        {{-- INSTANT LOADING STATE (Optimistic UI) --}}
        <div wire:loading wire:target="selectConversation" class="absolute inset-0 z-50 bg-[#efeae2] dark:bg-[#111b21] flex flex-col items-center justify-center animate-pulse">
            <svg class="animate-spin w-10 h-10 text-[#00a884] opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <p class="mt-4 text-[#667781] text-sm font-medium tracking-wide">Memuat obrolan...</p>
        </div>

        @if($activeConversationId)
            @php $activeConv = $conversations->where('id', $activeConversationId)->first(); @endphp

            {{-- Chat Header --}}
            <div class="flex items-center gap-3 px-4 py-2.5 bg-[#f0f2f5] dark:bg-[#202c33] shrink-0 shadow-sm z-10 transition-transform">
                {{-- Back Button (Mobile Only) --}}
                <button wire:click="$set('activeConversationId', null)" class="md:hidden w-10 h-10 -ml-2 rounded-full flex items-center justify-center text-[#54656f] dark:text-[#aebac1] hover:bg-black/10 transition-all duration-200 active:-translate-x-1 active:scale-90 shrink-0">
                    <svg viewBox="0 0 24 24" class="w-6 h-6" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                </button>
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white font-semibold shrink-0">
                    {{ strtoupper(substr($activeConv->name ?? $activeConv->phone_number, 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-[15px] text-zinc-900 dark:text-[#e9edef] leading-tight truncate">
                        {{ $activeConv->name ?? $activeConv->phone_number }}
                    </h3>
                    <p class="text-xs text-[#667781] dark:text-[#8696a0]">{{ $activeConv->phone_number }}</p>
                </div>
                {{-- Action Icons --}}
                <div class="flex items-center gap-1">
                    <button class="w-10 h-10 rounded-full flex items-center justify-center text-[#54656f] dark:text-[#aebac1] hover:bg-black/10 dark:hover:bg-white/10 transition-colors">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M15.009 13.805h-.636l-.22-.219a5.184 5.184 0 0 0 1.256-3.386 5.207 5.207 0 1 0-5.207 5.208 5.183 5.183 0 0 0 3.385-1.255l.221.22v.635l4.004 3.999 1.194-1.195-3.997-4.007zm-4.808 0a3.605 3.605 0 1 1 0-7.21 3.605 3.605 0 0 1 0 7.21z"/></svg>
                    </button>
                    <button class="w-10 h-10 rounded-full flex items-center justify-center text-[#54656f] dark:text-[#aebac1] hover:bg-black/10 dark:hover:bg-white/10 transition-colors">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M12 7a2 2 0 1 0-.001-4.001A2 2 0 0 0 12 7zm0 2a2 2 0 1 0-.001 3.999A2 2 0 0 0 12 9zm0 6a2 2 0 1 0-.001 3.999A2 2 0 0 0 12 15z"/></svg>
                    </button>
                </div>
            </div>

            {{-- Wrapper Area with WhatsApp Background Pattern --}}
            <div class="flex-1 relative flex flex-col bg-[#efeae2] dark:bg-[#0b141a] overflow-hidden z-0">
                
                {{-- WA Pattern Background (subtle) - Stays Fixed --}}
                <div class="absolute inset-0 opacity-[0.04] pointer-events-none" 
                     style="background-image: url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80'%3E%3Cpath fill='%23000' d='M0 0h80v80H0z'/%3E%3Cpath fill='none' stroke='%23fff' stroke-width='1' d='M0 40h80M40 0v80M20 0v80M60 0v80M0 20h80M0 60h80'/%3E%3C/svg%3E&quot;)">
                </div>

                {{-- Messages Scrolling Area --}}
                <div class="flex-1 overflow-y-auto px-4 py-4 relative z-10"
                     id="chat-messages-container"
                     style="scrollbar-width: thin; scrollbar-color: #ccc transparent;"
                     x-data
                     x-init="
                        $wire.on('chat-scrolled-to-bottom', () => {
                            setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 100);
                        });
                        setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 150);
                     ">

                @forelse($messages as $msg)
                    @php
                        $isOut = $msg->direction === 'out';
                    @endphp
                    <div class="flex {{ $isOut ? 'justify-end' : 'justify-start' }} mb-1">
                        <div class="relative max-w-[65%] group">
                            {{-- Message Bubble --}}
                            <div class="relative px-3 pt-2 pb-1 rounded-lg shadow-sm
                                {{ $isOut 
                                    ? 'bg-[#d9fdd3] text-zinc-800' 
                                    : 'bg-white dark:bg-[#202c33] text-zinc-800 dark:text-[#e9edef]' }}">
                                
                                {{-- Bubble tail --}}
                                @if($isOut)
                                    <div class="absolute -right-[7px] top-0 w-0 h-0" style="border-left: 8px solid #d9fdd3; border-bottom: 8px solid transparent;"></div>
                                @else
                                    <div class="absolute -left-[7px] top-0 w-0 h-0" style="border-right: 8px solid white; border-bottom: 8px solid transparent;"></div>
                                @endif

                                {{-- Media --}}
                                @if($msg->message_type === 'media' && $msg->media_url)
                                    <img src="{{ $msg->media_url }}" class="max-w-full rounded-md mb-1 object-cover max-h-64" alt="Media" />
                                @endif

                                {{-- Text --}}
                                <p class="text-[13.6px] leading-relaxed whitespace-pre-wrap break-words pr-12">{{ $msg->message }}</p>

                                {{-- Time + Status (bottom right, inside bubble) --}}
                                <div class="flex items-center justify-end gap-1 mt-0.5 -mb-0.5 float-right clear-both ml-3">
                                    <span class="text-[11px] text-[#667781] {{ $isOut ? '' : 'dark:text-[#8696a0]' }} whitespace-nowrap">
                                        {{ $msg->created_at->format('H:i') }}
                                    </span>
                                    @if($isOut)
                                        @if($msg->status === 'pending')
                                            {{-- Clock: pending/sedang dikirim --}}
                                            <svg class="w-3.5 h-3.5 text-[#667781] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 1.5"/></svg>
                                        @elseif($msg->status === 'sent')
                                            {{-- Centang 1 abu: terkirim ke server --}}
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" class="w-[18px] h-[18px] text-[#667781] shrink-0" fill="currentColor">
                                                <path d="M17.394 5.035l-.57-.444a.434.434 0 0 0-.609.076L8.397 14.019l-3.853-3.461a.434.434 0 0 0-.608.015l-.494.509a.434.434 0 0 0 .014.609l4.561 4.095a.434.434 0 0 0 .606-.039l8.785-10.127a.434.434 0 0 0-.014-.585z"/>
                                            </svg>
                                        @elseif($msg->status === 'delivered')
                                            {{-- Centang 2 abu: sudah diterima di HP --}}
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" class="w-[18px] h-[18px] text-[#667781] shrink-0" fill="currentColor">
                                                <path d="M17.394 5.035l-.57-.444a.434.434 0 0 0-.609.076L8.397 14.019l-3.853-3.461a.434.434 0 0 0-.608.015l-.494.509a.434.434 0 0 0 .014.609l4.561 4.095a.434.434 0 0 0 .606-.039l8.785-10.127a.434.434 0 0 0-.014-.585z"/>
                                                <path d="M13.394 5.035l-.57-.444a.434.434 0 0 0-.609.076l-5.752 7.026-.853-.766-.608.015-.494.509a.434.434 0 0 0 .014.609l1.561 1.402a.434.434 0 0 0 .606-.039l6.72-7.804a.434.434 0 0 0-.015-.584z" opacity=".65"/>
                                            </svg>
                                        @elseif($msg->status === 'read')
                                            {{-- Centang 2 BIRU: sudah dibaca --}}
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" class="w-[18px] h-[18px] text-[#53bdeb] shrink-0" fill="currentColor">
                                                <path d="M17.394 5.035l-.57-.444a.434.434 0 0 0-.609.076L8.397 14.019l-3.853-3.461a.434.434 0 0 0-.608.015l-.494.509a.434.434 0 0 0 .014.609l4.561 4.095a.434.434 0 0 0 .606-.039l8.785-10.127a.434.434 0 0 0-.014-.585z"/>
                                                <path d="M13.394 5.035l-.57-.444a.434.434 0 0 0-.609.076l-5.752 7.026-.853-.766-.608.015-.494.509a.434.434 0 0 0 .014.609l1.561 1.402a.434.434 0 0 0 .606-.039l6.72-7.804a.434.434 0 0 0-.015-.584z" opacity=".65"/>
                                            </svg>
                                        @elseif($msg->status === 'failed')
                                            {{-- Tanda seru merah: gagal kirim --}}
                                            <svg class="w-4 h-4 text-red-500 shrink-0" viewBox="0 0 24 24" fill="currentColor" title="{{ $msg->error_message }}"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                        @endif
                                    @endif
                                </div>
                                <div class="clear-both"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center h-full opacity-60">
                        <p class="text-sm text-[#667781] bg-[#ffffffcc] px-4 py-2 rounded-lg shadow-sm">
                            Belum ada pesan. Kirim pesan pertama! 👋
                        </p>
                    </div>
                @endforelse
            </div>
            </div>

            {{-- Input Bar (WhatsApp Mobile/Web Style) --}}
            <div class="flex flex-col gap-1 px-2 md:px-3 pt-2 pb-[calc(4px+env(safe-area-inset-bottom,0px))] bg-transparent md:bg-[#f0f2f5] md:dark:bg-[#202c33] shrink-0 z-20 w-full">
                
                <form wire:submit.prevent="sendMessage" class="flex items-end gap-1.5 w-full relative"
                      x-data="{ 
                          showAiSettings: false,
                          savedPersona: localStorage.getItem('ai_chat_persona') || '',
                          init() {
                              this.$watch('savedPersona', val => {
                                  localStorage.setItem('ai_chat_persona', val);
                                  $wire.set('aiPersona', val);
                              });
                              if (this.savedPersona) {
                                  $wire.set('aiPersona', this.savedPersona);
                              }
                          }
                      }"
                      @click.outside="showAiSettings = false">
                    
                    {{-- AI Settings Popover --}}
                    <div x-show="showAiSettings" x-transition.opacity
                         class="absolute left-0 bottom-[50px] mb-2 w-[320px] bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl p-4 z-50 pointer-events-auto"
                         x-cloak>
                        
                        <div class="mb-3">
                            <label class="block text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Persona AI (Tersimpan otomatis)</label>
                            <textarea x-model="savedPersona" rows="2" class="w-full bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-indigo-500 outline-none text-zinc-800 dark:text-zinc-200 resize-none" placeholder="Contoh: Nama Amel, umur 18 tahun, gadis desa yang ramah..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="block text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Instruksi Khusus (Sekali pakai)</label>
                            <textarea wire:model="aiInstruction" rows="2" class="w-full bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-indigo-500 outline-none text-zinc-800 dark:text-zinc-200 resize-none" placeholder="Contoh: Tolak permintaannya dengan sangat halus..."></textarea>
                        </div>
                        
                        <button type="button" @click="showAiSettings = false; $wire.generateAiReply()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 text-sm font-semibold flex items-center justify-center gap-2 transition-colors">
                            <flux:icon.sparkles class="w-4 h-4" /> Generate Balasan
                        </button>
                    </div>

                    {{-- The Input Pill --}}
                    <div class="flex-1 flex items-end bg-white dark:bg-[#1f2c34] rounded-[24px] md:rounded-xl shadow-sm overflow-hidden border border-zinc-100 dark:border-zinc-800 md:border-none min-h-[44px] cursor-text relative"
                         @click="document.getElementById('message-input-field').focus()">
                        
                        {{-- AI Settings Button --}}
                        <button type="button" @click.stop="showAiSettings = !showAiSettings" class="w-10 h-10 flex items-center justify-center text-zinc-400 hover:text-indigo-500 hover:bg-black/5 dark:hover:bg-white/5 transition-colors shrink-0 rounded-full ml-0.5 mb-[2px]">
                            <flux:icon.adjustments-horizontal class="w-[22px] h-[22px]" />
                        </button>
                        
                        {{-- AI Sparkles Button --}}
                        <button type="button" 
                                wire:click="generateAiReply"
                                class="w-10 h-10 flex items-center justify-center text-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors shrink-0 rounded-full mb-[2px] cursor-pointer"
                                title="Buat draf balasan dengan AI">
                            <span wire:loading.remove wire:target="generateAiReply">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                            </span>
                            <span wire:loading wire:target="generateAiReply">
                                <svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </span>
                        </button>

                        <textarea 
                            id="message-input-field"
                            x-ref="msgInput"
                            wire:model="messageInput" 
                            rows="1" 
                            class="flex-1 bg-transparent border-none focus:ring-0 resize-none px-2 py-2.5 text-[15px] text-zinc-900 dark:text-[#d1d7db] placeholder-[#8696a0] outline-none leading-normal w-full"
                            placeholder="Ketik pesan"
                            style="max-height: 120px; scrollbar-width: none;"
                            x-data 
                            x-init="$el.addEventListener('input', function() {
                                this.style.height = 'auto';
                                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                            })"
                            @chat-input-filled.window="
                                setTimeout(() => { 
                                    $el.dispatchEvent(new Event('input')); 
                                    $el.focus(); 
                                }, 100)
                            ">
                        </textarea>
                        
                        {{-- Attachment & Camera / Clear Buttons (Inside Pill, Right) --}}
                        <div class="flex items-center shrink-0 mb-[2px] mr-0.5"
                             x-data="{ hasText: false }"
                             @chat-input-filled.window="hasText = document.getElementById('message-input-field').value.length > 0"
                             x-init="
                                 hasText = document.getElementById('message-input-field').value.length > 0;
                                 document.getElementById('message-input-field').addEventListener('input', (e) => hasText = e.target.value.length > 0);
                             ">
                            
                            {{-- Clear (X) Button - Muncul hanya jika ada teks --}}
                            <button type="button" x-show="hasText" x-cloak
                                    @click="
                                        $wire.set('messageInput', ''); 
                                        document.getElementById('message-input-field').value = ''; 
                                        document.getElementById('message-input-field').dispatchEvent(new Event('input'));
                                        document.getElementById('message-input-field').focus();
                                    " 
                                    class="w-9 h-10 flex items-center justify-center text-[#8696a0] hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors rounded-full" 
                                    title="Bersihkan teks">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>

                            {{-- Attachment Button - Sembunyi jika ada teks --}}
                            <button type="button" x-show="!hasText" @click.stop class="w-9 h-10 flex items-center justify-center text-[#8696a0] hover:bg-black/5 dark:hover:bg-white/5 transition-colors rounded-full transform -rotate-45">
                                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M1.816 15.556v.002c0 1.502.584 2.912 1.646 3.972s2.472 1.647 3.974 1.647a5.58 5.58 0 0 0 3.972-1.645l9.547-9.548c.769-.768 1.147-1.767 1.058-2.817-.079-.968-.548-1.927-1.319-2.698-1.594-1.592-4.068-1.711-5.517-.262l-7.916 7.915c-.881.881-.792 2.25.214 3.261.959.958 2.423 1.053 3.263.215l5.511-5.512c.28-.28.267-.722.053-.936l-.244-.244c-.191-.191-.567-.349-.957.04l-5.506 5.506c-.18.18-.635.127-.976-.214-.098-.097-.576-.613-.213-.973l7.915-7.917c.818-.817 2.267-.699 3.23.262.5.501.802 1.1.849 1.685.051.573-.156 1.111-.589 1.543l-9.547 9.549a3.97 3.97 0 0 1-2.829 1.171 3.975 3.975 0 0 1-2.83-1.173 3.973 3.973 0 0 1-1.172-2.828c0-1.071.415-2.076 1.172-2.83l7.209-7.211c.157-.157.264-.579.028-.814L11.5 4.36a.57.57 0 0 0-.834.018l-7.205 7.207a5.577 5.577 0 0 0-1.645 3.971z"/></svg>
                            </button>
                            
                            {{-- Camera Button - Sembunyi jika ada teks --}}
                            <button type="button" x-show="!hasText" @click.stop class="w-9 h-10 flex items-center justify-center text-[#8696a0] hover:bg-black/5 dark:hover:bg-white/5 transition-colors rounded-full">
                                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M21.054 6.75h-3.923l-1.391-2.128A1.517 1.517 0 0 0 14.475 4h-4.95c-.482 0-.916.237-1.185.62l-1.391 2.13H3.025C1.91 6.75 1 7.66 1 8.775v10.125C1 20.015 1.91 20.925 3.025 20.925h18.03c1.115 0 2.025-.91 2.025-2.025V8.775c0-1.115-.91-2.025-2.026-2.025zm-9.016 11.2a4.9 4.9 0 1 1 0-9.8 4.9 4.9 0 0 1 0 9.8zm0-8.3a3.4 3.4 0 1 0 0 6.8 3.4 3.4 0 0 0 0-6.8z"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Send Button (WhatsApp Green Circle, Floating) --}}
                    <button type="submit" 
                            class="w-[44px] h-[44px] rounded-full bg-[#00a884] hover:bg-[#017561] flex items-center justify-center text-zinc-900 transition-all duration-200 active:scale-75 active:rotate-12 shadow-md shrink-0 mb-[1px]">
                        <svg viewBox="0 0 24 24" class="w-[20px] h-[20px] translate-x-0.5" fill="currentColor">
                            <path d="M1.101 21.757 23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"/>
                        </svg>
                    </button>
                </form>
            </div>

        @else
            {{-- Empty State (WhatsApp Desktop Style) --}}
            <div class="flex-1 flex flex-col items-center justify-center bg-[#f0f2f5] dark:bg-[#222e35]">
                <div class="flex flex-col items-center gap-5 max-w-sm text-center">
                    <div class="w-64 h-64 opacity-90">
                        <svg viewBox="0 0 303.083 303.083" fill="#25d366" xmlns="http://www.w3.org/2000/svg">
                            <path d="M237.525 39.783C211.616 14.283 177.244 0 140.682 0 63.526 0 .841 62.684.841 139.841c0 24.641 6.566 48.67 19.049 69.72L0 303.083l95.295-24.984c20.287 11.063 43.148 16.897 66.372 16.897h.057c77.108 0 139.366-62.683 139.359-139.841-.001-37.336-14.552-72.437-63.558-115.372zm-96.843 215.266h-.044c-20.884-.001-41.372-5.621-59.222-16.23l-4.25-2.52-44.073 11.558 11.758-42.934-2.767-4.405C30.781 182.836 24.843 161.709 24.843 139.84c0-63.771 51.903-115.674 115.723-115.674 30.893.001 59.921 12.041 81.778 33.899C244.2 79.921 256.174 108.9 256.118 139.74c-.086 63.82-52.022 115.309-115.436 115.309zm63.439-86.546c-3.479-1.739-20.584-10.153-23.773-11.315-3.188-1.161-5.508-1.739-7.826 1.74-2.32 3.481-8.987 11.316-11.017 13.635-2.028 2.32-4.059 2.609-7.538.869-3.479-1.739-14.689-5.413-27.966-17.26-10.33-9.221-17.304-20.608-19.335-24.088-2.028-3.479-.214-5.361 1.526-7.096 1.567-1.56 3.479-4.059 5.219-6.09 1.737-2.028 2.318-3.479 3.478-5.799 1.161-2.319.58-4.349-.29-6.09-.869-1.739-7.826-18.868-10.725-25.827-2.824-6.783-5.694-5.866-7.826-5.974-2.028-.103-4.349-.124-6.668-.124-2.319 0-6.089.869-9.278 4.349-3.188 3.479-12.159 11.894-12.159 29.007 0 17.114 12.449 33.647 14.189 35.966 1.739 2.319 24.503 37.422 59.375 52.479 8.296 3.583 14.769 5.724 19.82 7.327 8.33 2.649 15.907 2.275 21.895 1.379 6.682-.996 20.584-8.413 23.484-16.54 2.901-8.124 2.901-15.083 2.03-16.533-.87-1.452-3.19-2.32-6.669-4.061z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-[22px] font-light text-[#41525d] dark:text-[#e9edef] mb-3">WhatsApp Web</h2>
                        <p class="text-[14px] text-[#667781] dark:text-[#8696a0] leading-relaxed">
                            Kirim dan terima pesan WhatsApp langsung dari ERP Anda.<br>
                            Pilih percakapan di sebelah kiri untuk memulai.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-[13px] text-[#667781] dark:text-[#8696a0] border-t border-zinc-200 dark:border-[#2a3942] pt-4 w-full justify-center">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                        Pesan Anda dienkripsi dan aman
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- NEW CHAT MODAL --}}
    {{-- ============================================================ --}}
    <flux:modal wire:model="showNewChatModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Percakapan Baru</h2>
                <p class="text-sm text-zinc-500 mt-1">Cari kontak atau masukkan nomor baru.</p>
            </div>
            
            <!-- Contact Picker -->
            <div class="space-y-3">
                <flux:input wire:model.live.debounce.300ms="searchContact" icon="magnifying-glass" placeholder="Cari Pelanggan / Vendor..." />
                
                @if(strlen($searchContact) >= 2)
                    <div class="max-h-48 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-1 bg-zinc-50 dark:bg-zinc-800/50 custom-scrollbar">
                        @forelse($this->availableContacts as $contact)
                            <div wire:click="selectContact('{{ addslashes($contact['name']) }}', '{{ $contact['phone'] }}')" class="p-2 hover:bg-white dark:hover:bg-zinc-700 rounded cursor-pointer transition-colors flex justify-between items-center group">
                                <div class="overflow-hidden">
                                    <div class="font-medium text-sm text-zinc-800 dark:text-zinc-200 truncate">{{ $contact['name'] }}</div>
                                    <div class="text-xs text-zinc-500">{{ $contact['phone'] }}</div>
                                </div>
                                <flux:badge size="sm" color="{{ $contact['type'] == 'Customer' ? 'emerald' : 'sky' }}">{{ $contact['type'] }}</flux:badge>
                            </div>
                        @empty
                            <div class="p-4 text-center text-sm text-zinc-500">Tidak ada kontak ditemukan.</div>
                        @endforelse
                    </div>
                @endif
            </div>

            <flux:separator text="Atau ketik manual" />

            <form wire:submit.prevent="startNewChat" class="space-y-6">
                <div class="space-y-4">
                    <flux:input wire:model="newChatPhone" label="Nomor WhatsApp" placeholder="Contoh: 08123456789" type="tel" required />
                    <flux:input wire:model="newChatName" label="Nama Kontak (Opsional)" placeholder="Nama panggilan..." />
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('showNewChatModal', false)" variant="ghost">Batal</flux:button>
                    <flux:button type="submit" variant="primary" class="!bg-[#00a884] !hover:bg-[#017561]">Mulai Chat</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

</div>
