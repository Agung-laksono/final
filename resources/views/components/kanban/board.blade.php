@props([
    'componentId',
    'searchModel' => 'search',
    'searchPlaceholder' => 'Cari...',
    'viewMode' => 'kanban',
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'kanban-root relative flex flex-col w-full']) }}
     x-data="{ 
        showHeader: $persist(true).as('kanban-{{ $componentId }}-header-user-{{ auth()->id() }}'),
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
        Livewire.hook('commit', ({ component, succeed }) => {
            succeed(() => {
                const name = component.name || '';
                if (name.includes('kanban') || name.includes('index') || name.includes('sales-deliveries') || (component.el && component.el.querySelector('.kanban-root'))) {
                    processingId = null;
                    activeId = null;
                }
            })
        });
     "
     @status-updated.window="processingId = activeId"
     @modal-closed.window="activeId = null"
     style="height: 100vh; overflow: hidden;">
    
    <style>
        /* Paksa hilangkan padding bawaan layout KHUSUS untuk halaman Kanban ini
           - HANYA ketika kanban VISIBLE (parent wrapper tidak punya class 'hidden') */
        *:has(> :not(.hidden) > .kanban-root),
        body:has(:not(.hidden) > .kanban-root) main,
        body:has(:not(.hidden) > .kanban-root) *[data-flux-main],
        body:has(:not(.hidden) > .kanban-root) div[style*="grid-area: main"],
        body:has(:not(.hidden) > .kanban-root) .kanban-root-wrapper {
            padding: 0 !important;
            margin: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 0 !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            overflow: hidden !important;
        }
        
        body:has(:not(.hidden) > .kanban-root) {
            overflow: hidden !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        
        /* Menyembunyikan scrollbar tapi tetap bisa digulir */
        .hide-scroll::-webkit-scrollbar {
            display: none;
        }
        .hide-scroll {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
            height: 8px; /* Slightly thicker for horizontal scroll */
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
        
        <div class="flex-1 min-w-0 transition-all duration-500 ease-out" :class="searchFocused ? 'max-w-full' : 'max-w-md'">
            <flux:input 
                x-on:focus="searchFocused = window.innerWidth < 1098" 
                x-on:blur="searchFocused = false"
                wire:model.live.debounce.300ms="{{ $searchModel }}" 
                icon="magnifying-glass" 
                placeholder="{{ $searchPlaceholder }}"
                class="[&_input]:h-8 [&_input]:text-xs sm:[&_input]:h-9.5 sm:[&_input]:text-sm" />
        </div>

        <div class="flex items-center shrink-0 transition-all duration-500 ease-out origin-right overflow-hidden max-sm:[&_.flex.border]:h-8 max-sm:[&_.flex.border]:items-center max-sm:[&_button]:h-8 max-sm:[&_button]:!py-0 max-sm:[&_button]:!px-2 max-sm:[&_button]:!text-[9px] max-sm:[&_button_svg]:!w-3.5 max-sm:[&_button_svg]:!h-3.5 max-sm:[&_a]:!h-8 max-sm:[&_a]:!py-0 max-sm:[&_a]:!px-2 max-sm:[&_a]:!text-[9px] max-sm:[&_a_svg]:!w-3.5 max-sm:[&_a_svg]:!h-3.5 gap-1 sm:gap-2"
             :class="searchFocused ? 'max-w-0 opacity-0 scale-95 !gap-0 !mx-0' : 'max-w-[500px] opacity-100 scale-100'">


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
