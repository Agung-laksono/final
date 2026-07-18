@props(['searchModel' => null, 'searchPlaceholder' => 'Cari...'])

<div x-data="{ searchFocused: false, lastScroll: 0, show: true }"
     @scroll.window="
        let current = window.scrollY;
        if (current > 50 && current > lastScroll) { 
            show = false; 
        } else if (current < lastScroll) { 
            show = true; 
        }
        lastScroll = current;
     "
     class="sticky top-0 z-[60] flex items-center justify-between gap-1 sm:gap-4 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md px-2 py-1.5 sm:px-6 sm:py-3 mb-4 sm:mb-6 border border-zinc-200/50 dark:border-zinc-800/50 shadow-sm rounded-xl transition-transform duration-300"
     :class="show ? 'translate-y-0' : '-translate-y-full'">
     
    @if($searchModel)
    <div class="flex-1 min-w-0 transition-all duration-500 ease-out">
        <flux:input 
            x-on:focus="searchFocused = window.innerWidth < 1098" 
            x-on:blur="searchFocused = false"
            wire:model.live.debounce.300ms="{{ $searchModel }}" 
            icon="magnifying-glass" 
            placeholder="{{ $searchPlaceholder }}"
            class="[&_input]:h-8 [&_input]:text-xs sm:[&_input]:h-9.5 sm:[&_input]:text-sm" />
    </div>
    @endif
    
    <div class="flex items-center shrink-0 transition-all duration-500 ease-out origin-right overflow-hidden max-sm:[&_.flex.border]:h-8 max-sm:[&_.flex.border]:items-center max-sm:[&_button]:h-8 max-sm:[&_button]:!py-0 max-sm:[&_button]:!px-2 max-sm:[&_button]:!text-[9px] max-sm:[&_button_svg]:!w-3.5 max-sm:[&_button_svg]:!h-3.5 max-sm:[&_a]:!h-8 max-sm:[&_a]:!py-0 max-sm:[&_a]:!px-2 max-sm:[&_a]:!text-[9px] max-sm:[&_a_svg]:!w-3.5 max-sm:[&_a_svg]:!h-3.5 gap-1 sm:gap-2"
         :class="searchFocused ? 'max-w-0 opacity-0 scale-95 !gap-0 !mx-0' : 'max-w-[500px] opacity-100 scale-100'">
         
        {{ $slot }}
    </div>
</div>
