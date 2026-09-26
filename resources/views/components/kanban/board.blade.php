@props([
    'componentId',
    'workspaceId' => null,
    'searchModel' => 'search',
    'searchPlaceholder' => 'Cari...',
    'viewMode' => 'kanban',
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'kanban-root relative flex flex-col w-full']) }}
     x-data="{ 
        showHeader: localStorage.getItem('kanban-{{ $componentId }}-header-user-{{ auth()->id() }}') !== 'false',
        transparent: true,
        activeId: null,
        processingId: null,
        isDown: false,
        startX: 0,
        scrollLeft: 0,
        startDragging(e) {
            // Hanya aktifkan drag jika yang diklik adalah background/gap luar kolom
            if (e.target !== this.$refs.boardContainer && e.target !== this.$refs.boardContainer.firstElementChild) {
                return;
            }
            this.isDown = true;
            this.startX = e.pageX - this.$refs.boardContainer.offsetLeft;
            this.scrollLeft = this.$refs.boardContainer.scrollLeft;
        },
        stopDragging() {
            this.isDown = false;
        },
        drag(e) {
            if(!this.isDown) return;
            e.preventDefault();
            const x = e.pageX - this.$refs.boardContainer.offsetLeft;
            const walk = (x - this.startX) * 1.5; // Scroll-fast slightly reduced for smoother feel
            this.$refs.boardContainer.scrollLeft = this.scrollLeft - walk;
        }
     }" 
     x-init="
        $watch('showHeader', value => localStorage.setItem('kanban-{{ $componentId }}-header-user-{{ auth()->id() }}', value));
        Livewire.hook('commit', ({ component, succeed }) => {
            succeed(() => {
                processingId = null;
                activeId = null;
            })
        });
        
        $nextTick(() => {
            let mainContainer = $el.closest('[data-flux-main]');
            if(mainContainer) {
                mainContainer.style.setProperty('padding', '0', 'important');
                mainContainer.style.setProperty('margin', '0', 'important');
                mainContainer.style.setProperty('display', 'flex', 'important');
                mainContainer.style.setProperty('flex-direction', 'column', 'important');
                mainContainer.style.setProperty('height', '100dvh', 'important');
                mainContainer.style.setProperty('max-height', '100dvh', 'important');
                mainContainer.style.setProperty('min-height', '0', 'important');
            }
            document.body.style.setProperty('overflow', 'hidden', 'important');
            document.body.style.setProperty('padding', '0', 'important');
            document.body.style.setProperty('margin', '0', 'important');
        });
        
        @if($workspaceId)
        // Subscribe ke Pusher channel workspace untuk sinkronisasi realtime antar user
        $nextTick(() => {
            if (!window.Echo) return;
            
            // Debounce: cegah multiple rapid-fire reloads dalam 800ms
            let reloadDebounceTimer = null;

            window.Echo.channel('workspace.{{ $workspaceId }}')
                .listen('.WorkspaceTaskUpdated', (event) => {
                    // Smart delay berdasarkan prioritas aksi
                    const lowPriorityActions = ['comment_added', 'attachment_uploaded', 'task_updated'];
                    const delay = lowPriorityActions.includes(event.action) ? 1500 : 300;
                    
                    // Filter: Hanya tampilkan toast jika user saat ini adalah assignee di tugas tersebut
                    const currentUserId = {{ auth()->id() }};
                    const assigneeIds = event.data?.assignee_ids || [];
                    const isAssignee = assigneeIds.includes(currentUserId);
                    
                    if (isAssignee) {
                        const taskId = event.data?.task_id;
                        let cardIsVisible = false;
                        
                        if (taskId) {
                            const cardEl = document.querySelector(`[data-id='${taskId}']`);
                            if (cardEl) {
                                const rect = cardEl.getBoundingClientRect();
                                cardIsVisible = (
                                    rect.top >= 0 &&
                                    rect.left >= 0 &&
                                    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                                    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
                                );
                            }
                        }

                        if (cardIsVisible && taskId) {
                            // Munculkan inline toast di dalam card (disembunyikan global toast)
                            window.dispatchEvent(new CustomEvent(`task-inline-toast-${taskId}`, {
                                detail: {
                                    user: event.data.user,
                                    avatar: event.data.user_avatar,
                                    message: event.data.message
                                }
                            }));
                        } else {
                            // Munculkan toast notification global kustom (avatar + pesan) jika di luar layar
                            if (event.data && event.data.user && event.data.message) {
                                window.dispatchEvent(new CustomEvent('workspace-action-toast', {
                                    detail: {
                                        user: event.data.user,
                                        avatar: event.data.user_avatar,
                                        message: event.data.message,
                                        action: event.action
                                    }
                                }));
                            } else {
                                // Fallback jika message spesifik tidak dikirim
                                let actionMsg = 'Data diperbarui oleh rekan tim';
                                if (event.action === 'task_moved') actionMsg = 'Tugas dipindahkan';
                                else if (event.action === 'task_added') actionMsg = 'Tugas baru ditambahkan';
                                else if (event.action === 'comment_added') actionMsg = 'Komentar baru ditambahkan';
                                
                                if (typeof Flux !== 'undefined' && Flux.toast) {
                                    Flux.toast(actionMsg, { variant: 'success' });
                                }
                            }
                        }
                    }
                    
                    clearTimeout(reloadDebounceTimer);
                    reloadDebounceTimer = setTimeout(() => {
                        // 1. Reload board cards (kanban)
                        $wire.loadProjects();

                        // 2. Notify task-detail-modal jika sedang terbuka
                        //    Kirim task_id dari payload agar modal bisa filter apakah perlu reload
                        const taskId = event.data?.task_id ?? null;
                        Livewire.dispatch('workspace-task-changed', { taskId: taskId });
                    }, delay);
                });
        });
        @endif
     "
     @status-updated.window="
        processingId = activeId;
        setTimeout(() => { processingId = null; }, 1500);
     "
     @modal-closed.window="activeId = null; processingId = null;"
     style="height: 100vh; overflow: hidden;">
    
    <style>
        /* Menyembunyikan scrollbar tapi tetap bisa digulir */
        .hide-scroll::-webkit-scrollbar {
            display: none;
        }
        .hide-scroll {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .custom-scrollbar::-webkit-scrollbar {
            width: 3px;
            height: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 10px;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #334155;
        }
        .vertical-text {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
        }
    </style>
     
    {{-- Floating Show Header Button --}}
    <div class="absolute top-2 right-2 sm:top-4 sm:right-6 z-[110]" x-show="!showHeader" x-transition x-cloak>
        <flux:button variant="subtle" class="rounded-full shadow-lg bg-white/90 dark:bg-zinc-800/90 backdrop-blur border border-zinc-200 dark:border-zinc-700 w-10 h-10 p-0 flex items-center justify-center" @click="showHeader = true" title="Tampilkan Alat">
            <flux:icon.chevron-down class="w-5 h-5 text-zinc-500" />
        </flux:button>
    </div>

    {{-- Floating Controls (Full Width) --}}
    <div x-data="{ searchFocused: false }" class="absolute top-0 left-0 right-0 sm:top-2 sm:left-2 sm:right-2 z-[60] flex items-center justify-between gap-1 sm:gap-4 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md px-1.5 py-1 sm:px-3 sm:py-2.5 rounded-none sm:rounded-2xl shadow-sm border-b sm:border border-zinc-200/50 dark:border-zinc-800/50" x-show="showHeader" x-transition>
        
        <div class="flex flex-1 items-center gap-3 lg:gap-4 min-w-0">
            @if($title)
            <div class="hidden lg:flex flex-col shrink-0 pl-1">
                <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100 leading-none">{{ $title }}</div>
                @if($subtitle)
                <div class="text-[10px] font-medium text-zinc-500 mt-1 leading-none flex items-center gap-1">
                    <span>{{ $subtitle }}</span>
                    {{ $subtitleSuffix ?? '' }}
                </div>
                @endif
            </div>
            @endif

            <div class="flex-1 min-w-0 transition-all duration-500 ease-out" :class="searchFocused ? 'max-w-full' : 'max-w-md'">
                <flux:input 
                    x-on:focus="searchFocused = window.innerWidth < 1098" 
                    x-on:blur="searchFocused = false"
                    wire:model.live.debounce.300ms="{{ $searchModel }}" 
                    icon="magnifying-glass" 
                    placeholder="{{ $searchPlaceholder }}"
                    class="[&_input]:h-8 [&_input]:text-xs sm:[&_input]:h-9.5 sm:[&_input]:text-sm" />
            </div>
        </div>

        <div class="flex items-center shrink-0 transition-all duration-500 ease-out origin-right max-sm:[&_.flex.border]:h-8 max-sm:[&_.flex.border]:items-center max-sm:[&_button]:h-8 max-sm:[&_button]:!py-0 max-sm:[&_button]:!px-2 max-sm:[&_button]:!text-[9px] max-sm:[&_button_svg]:!w-3.5 max-sm:[&_button_svg]:!h-3.5 max-sm:[&_a]:!h-8 max-sm:[&_a]:!py-0 max-sm:[&_a]:!px-2 max-sm:[&_a]:!text-[9px] max-sm:[&_a_svg]:!w-3.5 max-sm:[&_a_svg]:!h-3.5 gap-1 sm:gap-2"
             :class="searchFocused ? 'max-w-0 opacity-0 scale-95 !gap-0 !mx-0 overflow-hidden' : 'max-w-[800px] opacity-100 scale-100 overflow-visible'">


            @if(isset($actions) || isset($header_actions))
                <div class="w-px h-6 bg-zinc-200 dark:bg-zinc-700 mx-1 sm:mx-2 hidden sm:block"></div>
                {{ $actions ?? '' }}
                {{ $header_actions ?? '' }}
            @endif

            <flux:button variant="subtle" class="px-1.5 sm:px-3 h-8 sm:h-auto text-zinc-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 ml-0.5 sm:ml-2" title="Sembunyikan Alat" @click="showHeader = false">
                <flux:icon.eye-slash class="w-4 h-4 sm:w-5 sm:h-5" />
            </flux:button>
        </div>
    </div>

    {{-- Kanban / Table Area --}}
    <div class="flex-1 min-h-0 flex flex-col px-0 lg:px-6 transition-all duration-300"
         :class="showHeader ? 'pt-[50px] sm:pt-[70px] lg:pt-[74px]' : 'pt-1 lg:pt-2'">
        
        @if($viewMode === 'kanban')
            <div x-ref="boardContainer"
                 @mousedown="startDragging" 
                 @mouseleave="stopDragging" 
                 @mouseup="stopDragging" 
                 @mousemove="drag"
                 class="flex-1 min-h-0 overflow-x-auto pb-0 snap-x snap-mandatory scroll-smooth custom-scrollbar"
                 :class="isDown ? 'cursor-grabbing select-none' : ''">
                 
                 @if(isset($kanban_layout))
                    {{ $kanban_layout }}
                 @else
                    <div class="flex gap-3 sm:gap-4 lg:gap-6 items-stretch min-w-full w-max h-full px-2 lg:px-0 before:content-[''] before:m-auto after:content-[''] after:m-auto">
                        {{ $slot }}
                    </div>
                 @endif
            </div>
        @elseif($viewMode === 'table')
            <div class="flex-1 min-h-0 overflow-y-auto w-full custom-scrollbar">
                {{ $table_layout ?? '' }}
            </div>
        @endif
    </div>
</div>
