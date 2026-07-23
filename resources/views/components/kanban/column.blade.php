@props([
    'statusKey',
    'column',
    'componentId',
    'count' => 0,
    'defaultCollapsed' => false
])

<div x-data="{ collapsed: $persist({{ $defaultCollapsed ? 'true' : 'false' }}).as('kanban-col-{{ $componentId }}-{{ $statusKey }}-user-{{ auth()->id() }}') }"
     style="height: 100%; display: flex; flex-direction: column;"
     class="flex-shrink-0 rounded-xl transition-all duration-300 snap-center"
     :class="(collapsed ? 'w-16 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800/80' : 'w-80')"
     @click="if(collapsed) collapsed = false"
     wire:key="kanban-column-{{ $componentId }}-{{ $statusKey }}">
    
    {{-- Column Header --}}
    <div class="px-4 py-1.5 lg:py-4 flex justify-between items-center rounded-t-xl transition-all duration-300"
         :class="(collapsed ? 'flex-col gap-4 h-full pb-8' : '')">
        <div class="flex items-center gap-2 min-w-0" :class="collapsed ? 'flex-col' : ''">
            <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500 shadow-[0_0_8px_rgba(0,0,0,0.5)] shadow-{{ $column['color'] }}-500/50 shrink-0"></div>
            <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 transition-all duration-300 whitespace-nowrap"
                :class="collapsed ? 'vertical-text tracking-widest mt-2' : ''">{{ $column['title'] }}</h3>
        </div>
        <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
            @if(isset($headerActions))
                <div x-show="!collapsed" class="flex items-center mr-1">
                    {{ $headerActions }}
                </div>
            @endif
            <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800 shrink-0">{{ $count }}</flux:badge>
            <flux:button size="sm" variant="subtle" class="!px-1.5 !py-1.5 shrink-0" @click.stop="collapsed = !collapsed" x-bind:title="collapsed ? 'Buka Kolom' : 'Tutup Kolom'">
                <flux:icon.arrows-up-down x-show="collapsed" class="w-4 h-4" />
                <flux:icon.arrows-right-left x-show="!collapsed" class="w-4 h-4" />
            </flux:button>
        </div>
    </div>

    {{-- Column Content (Cards) --}}
    <div x-show="!collapsed" x-transition.opacity.duration.300ms class="flex-1 overflow-y-auto p-3 space-y-3 hide-scroll">
        {{ $slot }}
    </div>
</div>
