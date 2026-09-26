@props([
    'statusKey',
    'column',
    'componentId',
    'count' => 0,
    'defaultCollapsed' => false
])

<div x-data="{ collapsed: $persist({{ $defaultCollapsed ? 'true' : 'false' }}).as('kanban-col-{{ $componentId }}-{{ $statusKey }}-user-{{ auth()->id() }}') }"
     style="height: 100%; display: flex; flex-direction: column;"
     class="flex-shrink-0 rounded-xl transition-all duration-300 snap-center bg-transparent"
     :class="(collapsed ? 'w-16 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800/80' : 'w-80')"
     @click="if(collapsed) collapsed = false"
     wire:key="kanban-column-{{ $componentId }}-{{ $statusKey }}">
    
    {{-- Column Header --}}
    <div class="px-4 py-1.5 lg:py-4 flex justify-between items-center rounded-t-xl transition-all duration-300 column-header-handle cursor-grab active:cursor-grabbing"
         :class="(collapsed ? 'flex-col gap-4 h-full pb-8' : '')">
        <div class="flex items-start gap-2 min-w-0" :class="collapsed ? 'flex-col items-center' : ''">
            @php
                $rawColor = $column['color'] ?? 'gray';
                $colorMap = [
                    'slate' => '#64748b', 'gray' => '#6b7280', 'zinc' => '#71717a',
                    'red' => '#ef4444', 'orange' => '#f97316', 'amber' => '#f59e0b',
                    'green' => '#22c55e', 'emerald' => '#10b981', 'teal' => '#14b8a6', 'cyan' => '#06b6d4',
                    'blue' => '#3b82f6', 'indigo' => '#6366f1', 'violet' => '#8b5cf6',
                    'purple' => '#a855f7', 'pink' => '#ec4899', 'rose' => '#f43f5e'
                ];
                $hexColor = $colorMap[$rawColor] ?? (str_starts_with($rawColor, '#') ? $rawColor : '#6b7280');
            @endphp
            <div class="w-2.5 h-2.5 rounded-full shrink-0 mt-1.5" style="background-color: {{ $hexColor }}; box-shadow: 0 0 8px {{ $hexColor }}80;"></div>
            <div class="flex flex-col">
                <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 transition-all duration-300 whitespace-nowrap"
                    :class="collapsed ? 'vertical-text tracking-widest mt-2' : ''">{{ $column['title'] }}</h3>
                @if(isset($headerSubtitle))
                    <div x-show="!collapsed" class="text-[10px] font-bold mt-0.5" style="color: {{ $hexColor }}; opacity: 0.8;">
                        {!! $headerSubtitle !!}
                    </div>
                @endif
            </div>
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
