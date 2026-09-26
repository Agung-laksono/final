<x-kanban.column
    :status-key="$statusKey"
    :column="$columnData"
    component-id="workspace-{{ $workspace->id }}"
    :count="$columnProjects->count()"
>
    {{-- Column Header Actions --}}
    @if($isAdminOrLeader)
    <x-slot:headerActions>
        <flux:dropdown position="bottom end">
            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="!px-1.5" />
            <flux:menu>
                <flux:menu.item wire:click="showAddTask('{{ $statusKey }}')" icon="plus">Tambah Task</flux:menu.item>
                <flux:menu.item wire:click="editColumn('{{ $statusKey }}')" icon="pencil">Edit Kolom</flux:menu.item>
                @if($canMakeGroup ?? false)
                    <flux:menu.item wire:click="makeColumnGroup('{{ $statusKey }}')" icon="squares-plus">Jadikan Grup Kolom</flux:menu.item>
                @endif
                <flux:menu.separator />
                <flux:menu.item wire:click="deleteColumn('{{ $statusKey }}')" wire:confirm="Hapus kolom '{{ $columnData['title'] }}' dan semua task di dalamnya?" icon="trash" class="text-red-600 hover:bg-red-50">Hapus Kolom</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </x-slot:headerActions>
    @endif

    <div class="kanban-col-container p-2 flex flex-col gap-2 hide-scroll min-h-[50px]"
         id="kanban-col-{{ $statusKey }}"
         data-status="{{ $statusKey }}"
         x-data="{
            init() {
                window.Sortable.create(this.$el, {
                    group: 'kanban-cards',
                    animation: 150,
                    draggable: '.is-draggable',
                    ghostClass: 'opacity-40',
                    dragClass: 'rotate-2',
                    emptyInsertThreshold: 50,
                    swapThreshold: 0.65,
                    onEnd: (e) => {
                        const newStatus = e.to.getAttribute('data-status');
                        const orderedIds = Array.from(e.to.children)
                            .filter(c => c.hasAttribute('data-id'))
                            .map(c => c.getAttribute('data-id'));
                        this.$wire.reorder(orderedIds, newStatus);
                    }
                });
            }
         }">
         
        <div class="empty-state hidden flex-col items-center justify-center py-8 border-2 border-dashed border-zinc-200 dark:border-zinc-700/60 rounded-lg bg-zinc-50/50 dark:bg-zinc-800/20">
             <span class="text-sm font-medium text-zinc-400 dark:text-zinc-500">Kosong</span>
        </div>

        @foreach($columnProjects as $project)
            @php
                $totalSub = isset($project['subtasks']) ? count($project['subtasks']) : 0;
                $completedSub = isset($project['subtasks']) ? collect($project['subtasks'])->where('is_completed', true)->count() : 0;
                $isAssignedToMe = !empty($project['assignees']) && collect($project['assignees'])->contains('id', auth()->id());
                $canDrag = $isAdminOrLeader || $isAssignedToMe;
                
                $hasUnreadUpdate = false;
                if (!empty($project['last_significant_update_at'])) {
                    $updateTime = \Carbon\Carbon::parse($project['last_significant_update_at']);
                    $lastReadAt = !empty($project['user_reads']) ? $project['user_reads'][0]['last_read_at'] : null;
                    if (!$lastReadAt || $updateTime->gt(\Carbon\Carbon::parse($lastReadAt))) {
                        $hasUnreadUpdate = true;
                    }
                }
                
                $priorityConfig = match($project['priority'] ?? 'normal') {
                    'critical' => ['label' => 'Critical', 'color' => '#ef4444'],
                    'high'     => ['label' => 'High', 'color' => '#f97316'],
                    'normal'   => ['label' => 'Normal', 'color' => '#e4e4e7'], // Default grey border for normal
                    'low'      => ['label' => 'Low', 'color' => '#94a3b8'],
                    default    => ['label' => 'Normal', 'color' => '#e4e4e7'],
                };
                
                $borderColor = $priorityConfig['color'];
            @endphp
            <div x-data="{
                    inlineToast: null,
                    showInlineToast(detail) {
                        this.inlineToast = detail;
                        setTimeout(() => this.inlineToast = null, 3000);
                    },
                    init() {
                        let observer = new MutationObserver((mutations) => {
                            mutations.forEach((m) => {
                                if (m.attributeName === 'data-update-time') {
                                    this.$el.classList.remove('animate-card-shake');
                                    void this.$el.offsetWidth; // Force CSS reflow
                                    this.$el.classList.add('animate-card-shake');
                                }
                            });
                        });
                        observer.observe(this.$el, { attributes: true });
                    }
                 }"
                 @task-inline-toast-{{ $project['id'] }}.window="showInlineToast($event.detail)"
                 data-update-time="{{ $project['last_significant_update_at'] ?? '' }}"
                 @click="$flux.modal('task-detail').show(); window.dispatchEvent(new CustomEvent('task-loading')); Livewire.dispatch('open-task-modal', { taskId: {{ $project['id'] }}, columns: {{ json_encode($columns) }} })"
                 data-id="{{ $project['id'] }}"
                 class="bg-white dark:bg-zinc-900 rounded-lg p-2.5 shadow-sm border border-zinc-200 dark:border-zinc-800 group relative {{ $canDrag ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer' }} hover:bg-zinc-50 dark:hover:bg-zinc-800/80 hover:border-indigo-300 dark:hover:border-indigo-700 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md {{ $canDrag ? 'is-draggable' : '' }} {{ $hasUnreadUpdate ? 'animate-card-shake' : '' }}"
                 style="{{ $borderColor ? 'border-left: 4px solid ' . $borderColor . ';' : '' }}">

                {{-- Inline Toast Overlay --}}
                <div x-show="inlineToast" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     style="display: none;"
                     class="absolute inset-0 z-50 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md rounded-lg flex flex-col items-center justify-center p-4 text-center shadow-xl ring-2 ring-sky-400 ring-offset-2 ring-offset-white dark:ring-offset-zinc-900 overflow-hidden">
                    
                    {{-- Decorative background glow --}}
                    <div class="absolute inset-0 bg-gradient-to-tr from-sky-500/10 to-indigo-500/10"></div>
                    
                    <div class="relative z-10 flex flex-col items-center">
                        <div class="relative mb-2">
                            <div class="absolute inset-0 bg-sky-400 rounded-full animate-ping opacity-20"></div>
                            <img :src="inlineToast?.avatar" class="relative w-12 h-12 rounded-full shadow-md object-cover ring-2 ring-white dark:ring-zinc-800" x-show="inlineToast?.avatar">
                        </div>
                        <div class="text-sm font-extrabold text-zinc-800 dark:text-zinc-100 tracking-tight" x-text="inlineToast?.user"></div>
                        <div class="text-xs font-semibold text-sky-600 dark:text-sky-400 mt-1 bg-sky-50 dark:bg-sky-900/30 px-2 py-0.5 rounded-full shadow-sm" x-text="inlineToast?.message"></div>
                    </div>
                </div>

                @if($hasUnreadUpdate)
                    {{-- Full Card Wave Animation Layer --}}
                    <div class="absolute inset-0 z-0 rounded-lg pointer-events-none overflow-hidden animate-card-wave" title="Pembaruan Baru (Belum Dibaca)"></div>
                    
                    {{-- NEW Ribbon --}}
                    <div class="absolute top-0 right-0 z-10 flex">
                        <span class="relative inline-flex items-center justify-center rounded-bl-lg rounded-tr-lg bg-sky-500 px-2 py-0.5 text-[9px] font-extrabold tracking-wider text-white shadow-sm ring-1 ring-sky-600/50">NEW</span>
                    </div>
                @endif

                @php
                    $hasLabels = !empty($project['labels']);
                    $hasPriority = !empty($priorityConfig['label']);
                    $hasPlatform = !empty($project['platform']) && $project['platform'] !== 'General';
                    $hasTags = $hasLabels || $hasPriority || $hasPlatform;

                    // Deadline countdown
                    $dueLabel = null;
                    $dueClass = 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400';
                    if ($project['due_date'] && empty($project['completed_at'])) {
                        $due = \Carbon\Carbon::parse($project['due_date'])->startOfDay();
                        $today = \Carbon\Carbon::today();
                        $diff = (int) $today->diffInDays($due, false);
                        if ($diff < 0) {
                            $dueLabel = abs((int)$diff) . 'h terlambat';
                            $dueClass = 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 font-semibold';
                        } elseif ($diff == 0) {
                            $dueLabel = 'Hari ini';
                            $dueClass = 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 font-semibold';
                        } elseif ($diff == 1) {
                            $dueLabel = 'Besok';
                            $dueClass = 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 font-semibold';
                        } elseif ($diff <= 7) {
                            $dueLabel = $diff . ' hari lagi';
                            $dueClass = 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400';
                        } else {
                            $dueLabel = $due->format('d M');
                            $dueClass = 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400';
                        }
                    } elseif ($project['due_date'] && !empty($project['completed_at'])) {
                        $dueLabel = \Carbon\Carbon::parse($project['due_date'])->format('d M');
                        $dueClass = 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 line-through';
                    }
                @endphp

                {{-- Top: title + deadline badge --}}
                <div class="flex justify-between items-start gap-2 mb-1.5">
                    <div class="flex items-start gap-1.5">
                        <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100 leading-snug">{{ $project['title'] }}</h4>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        @if($dueLabel)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold whitespace-nowrap {{ $dueClass }}">
                            {{ $dueLabel }}
                        </span>
                        @endif
                        @if($canDrag)
                        <div @click.stop>
                            <flux:dropdown position="bottom end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="!px-1 !py-0 text-zinc-400 opacity-0 group-hover:opacity-100 transition-opacity" />
                                <flux:menu>
                                    <flux:menu.heading>Pindah ke...</flux:menu.heading>
                                    @foreach($columns as $optKey => $optData)
                                        @if($optKey !== $statusKey)
                                            <flux:menu.item wire:click="move({{ $project['id'] }}, '{{ $optKey }}')">{{ $optData['title'] }}</flux:menu.item>
                                        @endif
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Tags: Priority + Platform + Labels --}}
                @if($hasTags)
                <div class="flex flex-wrap gap-1 mb-2">
                    @if($hasPriority)
                        <span class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset"
                              style="background-color: {{ $priorityConfig['color'] }}15; color: {{ $priorityConfig['color'] }}; box-shadow: inset 0 0 0 1px {{ $priorityConfig['color'] }}30;">
                            ↑ {{ $priorityConfig['label'] }}
                        </span>
                    @endif
                    @if($hasPlatform)
                        <span class="inline-flex items-center rounded-md bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 text-[10px] font-medium text-zinc-700 dark:text-zinc-300 ring-1 ring-inset ring-zinc-500/20">{{ $project['platform'] }}</span>
                    @endif
                    @if($hasLabels)
                        @foreach($project['labels'] as $label)
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold tracking-wide"
                                  style="background-color: {{ $label['color'] }}20; color: {{ $label['color'] }}; border: 1px solid {{ $label['color'] }}40;">
                                {{ strtoupper($label['name']) }}
                            </span>
                        @endforeach
                    @endif
                </div>
                @endif

                {{-- Subtask progress bar --}}
                @if($totalSub > 0)
                <div class="mb-2 space-y-1.5 mt-2">
                    <div class="flex items-center justify-between text-[10px] font-bold text-zinc-500 mb-0.5">
                        <span class="flex items-center gap-1"><flux:icon.check-circle class="w-3 h-3 text-zinc-400"/> Progress</span>
                        <span class="{{ $completedSub == $totalSub ? 'text-green-600 dark:text-green-500' : 'text-indigo-600 dark:text-indigo-400' }}">{{ $completedSub }}/{{ $totalSub }}</span>
                    </div>
                    <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                        <div class="h-1.5 rounded-full transition-all duration-500 {{ $completedSub == $totalSub ? 'bg-green-500' : 'bg-indigo-500' }}"
                             style="width: {{ $totalSub > 0 ? round(($completedSub / $totalSub) * 100) : 0 }}%"></div>
                    </div>
                </div>
                @endif

                {{-- Footer: activity icons + assignees --}}
                <div class="flex items-center justify-between text-zinc-400 dark:text-zinc-500 mt-auto pt-1">
                    <div class="flex items-center gap-2.5 text-xs">
                        {{-- Comments count --}}
                        @if(($project['comments_count'] ?? 0) > 0)
                        <span class="flex items-center gap-0.5" title="{{ $project['comments_count'] }} komentar">
                            <flux:icon.chat-bubble-left class="w-3 h-3"/>
                            {{ $project['comments_count'] }}
                        </span>
                        @endif

                        {{-- Attachments count --}}
                        @if(($project['attachments_count'] ?? 0) > 0)
                        <span class="flex items-center gap-0.5" title="{{ $project['attachments_count'] }} lampiran">
                            <flux:icon.paper-clip class="w-3 h-3"/>
                            {{ $project['attachments_count'] }}
                        </span>
                        @endif

                        {{-- URLs count --}}
                        @if(($project['urls_count'] ?? 0) > 0)
                        <span class="flex items-center gap-0.5" title="{{ $project['urls_count'] }} link">
                            <flux:icon.link class="w-3 h-3"/>
                            {{ $project['urls_count'] }}
                        </span>
                        @endif
                    </div>

                    {{-- Assignees --}}
                    <div class="flex -space-x-1.5 shrink-0">
                        @forelse($project['assignees'] as $assignee)
                            <div class="w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900/30 ring-2 ring-white dark:ring-zinc-900 flex items-center justify-center text-[10px] font-bold text-indigo-700 dark:text-indigo-300 shadow-sm overflow-hidden" title="{{ $assignee['name'] }}">
                                @if(!empty($assignee['avatar']))
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($assignee['avatar']) }}" class="w-full h-full object-cover">
                                @else
                                    {{ substr($assignee['name'], 0, 1) }}
                                @endif
                            </div>
                        @empty
                            <div class="w-6 h-6 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-[10px] font-bold text-zinc-400 ring-2 ring-white dark:ring-zinc-900 border border-dashed border-zinc-300 dark:border-zinc-600" title="Unassigned">
                                <flux:icon.user class="w-3 h-3" />
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Add Task Inline Form --}}
        @if($isAdminOrLeader)
            @if($addingTaskToColumn == $statusKey)
                <div class="bg-white dark:bg-zinc-800 p-3 rounded-lg border border-indigo-200 dark:border-indigo-800/30 shadow-sm space-y-2">
                    <flux:input wire:model="newTaskTitle" placeholder="Judul task baru..." wire:keydown.enter="addTask" />
                    <div class="flex items-center gap-2 justify-end mt-2">
                        <flux:button wire:click="cancelAddTask" size="xs" variant="ghost">Batal</flux:button>
                        <flux:button wire:click="addTask" size="xs" variant="primary">Tambah</flux:button>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-kanban.column>
