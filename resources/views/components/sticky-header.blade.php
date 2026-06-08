<div 
    x-data="{ 
        lastScroll: 0, 
        show: true,
        scrolled: false
    }"
    @scroll.window="
        let current = window.scrollY;
        scrolled = current > 20;
        if (current > 50 && current > lastScroll) { 
            show = false; 
        } else if (current < lastScroll) { 
            show = true; 
        }
        lastScroll = current;
    "
    :class="[
        show ? 'translate-y-0' : '-translate-y-[120%]',
        scrolled ? 'bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md shadow-sm border-b border-zinc-200 dark:border-zinc-800' : 'bg-transparent border-transparent'
    ]"
    {{ $attributes->merge(['class' => 'sticky top-0 z-10 transition-all duration-300 ease-out pt-4 pb-4 px-4 -mx-4 -mt-4 ']) }}
>
    {{ $slot }}
</div>
