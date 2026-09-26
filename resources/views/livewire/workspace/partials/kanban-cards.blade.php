@foreach($columnProjects as $project)
    @php
        $totalSub = isset($project['subtasks']) ? count($project['subtasks']) : 0;
        $completedSub = isset($project['subtasks']) ? collect($project['subtasks'])->where('is_completed', true)->count() : 0;
        $subProgress = $totalSub > 0 ? round(($completedSub / $totalSub) * 100) : 0;
        $isAssignedToMe = !empty($project['assignees']) && collect($project['assignees'])->contains('id', auth()->id());
        $isBacklog = isset($isBacklogColumn) && $isBacklogColumn;
        $canDrag = $isAdminOrLeader || ($isAssignedToMe && !$isBacklog);
        
        // Get first label color (ensure it handles both collection and array)
        $firstLabel = !empty($project['labels']) ? (is_array($project['labels']) ? array_values($project['labels'])[0] : $project['labels']->first()) : null;
        $firstLabelColor = $firstLabel['color'] ?? ($firstLabel->color ?? null);
        
        // Count stats (if available in relationships, otherwise default to 0 for UI)
        $commentCount = isset($project['comments']) ? count($project['comments']) : 0;
        $attachmentCount = isset($project['attachments']) ? count($project['attachments']) : 0;
        $linkCount = isset($project['urls']) ? count($project['urls']) : 0;
        
        // Due Date Logic
        $dueText = '';
        $dueColor = 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400';
        if ($project['due_date']) {
            $dueDate = \Carbon\Carbon::parse($project['due_date'])->startOfDay();
            $today = \Carbon\Carbon::today();
            $diffDays = $today->diffInDays($dueDate, false);
            
            if ($diffDays < 0) {
                $dueText = 'Terlambat ' . abs($diffDays) . ' hari';
                $dueColor = 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
            } elseif ($diffDays == 0) {
                $dueText = 'Hari ini';
                $dueColor = 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
            } elseif ($diffDays <= 7) {
                $dueText = $diffDays . ' hari lagi';
                $dueColor = 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
            } else {
                $dueText = $dueDate->format('d M');
            }
        }
    @endphp
    <div @click="$flux.modal('task-detail').show(); window.dispatchEvent(new CustomEvent('task-loading')); Livewire.dispatch('open-task-modal', { taskId: {{ $project['id'] }}, columns: {{ json_encode($columns) }} })"
         data-id="{{ $project['id'] }}"
         class="bg-white dark:bg-zinc-900 rounded-xl p-3 shadow-sm border border-zinc-200 dark:border-zinc-800 group relative cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 {{ $canDrag ? 'is-draggable' : '' }}"
         style="{{ $firstLabelColor ? 'border-left: 4px solid ' . $firstLabelColor . ';' : '' }}">

        <div class="flex justify-between items-start mb-2 gap-2">
            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100 leading-snug">{{ $project['title'] }}</h4>
            <div class="flex items-center gap-1 shrink-0">
                @if($dueText)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold whitespace-nowrap {{ $dueColor }}">
                    {{ $dueText }}
                </span>
                @endif
                
                @if($canDrag)
                <div class="relative" @click.stop>
                    <flux:dropdown>
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="!px-1 !py-0 h-5 text-zinc-400 opacity-0 group-hover:opacity-100 transition-opacity" />
                        <flux:menu>
                            <flux:menu.heading>Pindah ke...</flux:menu.heading>
                            @foreach($columns as $optKey => $optData)
                                @if($optData['id'] !== $statusKey && empty(collect($columns)->where('parent_id', $optData['id'])->count()))
                                    <flux:menu.item wire:click="move({{ $project['id'] }}, '{{ $optData['id'] }}')">{{ $optData['title'] }}</flux:menu.item>
                                @endif
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                </div>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-1.5 mb-3">
            @if(!empty($project['platform']))
                <span class="inline-flex items-center rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-300">{{ strtoupper($project['platform']) }}</span>
            @endif
            @if(!empty($project['labels']))
                @foreach($project['labels'] as $label)
                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold tracking-wide"
                          style="background-color: {{ $label['color'] }}20; color: {{ $label['color'] }}; border: 1px solid {{ $label['color'] }}40;">
                        {{ strtoupper($label['name']) }}
                    </span>
                @endforeach
            @endif
        </div>

        @if($totalSub > 0)
        <div class="mb-3">
            <div class="flex items-center justify-between text-[10px] font-bold text-zinc-500 mb-1">
                <span class="flex items-center gap-1"><flux:icon.check-circle class="w-3 h-3 text-zinc-400"/> Progress</span>
                <span class="{{ $completedSub == $totalSub ? 'text-green-600 dark:text-green-500' : 'text-indigo-600 dark:text-indigo-400' }}">{{ $completedSub }}/{{ $totalSub }}</span>
            </div>
            <div class="h-1.5 w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500 {{ $completedSub == $totalSub ? 'bg-green-500' : 'bg-indigo-500' }}" style="width: {{ $subProgress }}%"></div>
            </div>
        </div>
        @endif

        <div class="flex items-center justify-between mt-auto pt-2 border-t border-zinc-100 dark:border-zinc-800/50">
            <div class="flex items-center gap-3 text-zinc-400 dark:text-zinc-500">
                @if($commentCount > 0)
                <div class="flex items-center gap-1 text-[11px] font-medium hover:text-indigo-500 transition-colors">
                    <flux:icon.chat-bubble-left class="w-3.5 h-3.5"/>
                    <span>{{ $commentCount }}</span>
                </div>
                @endif
                @if($attachmentCount > 0)
                <div class="flex items-center gap-1 text-[11px] font-medium hover:text-indigo-500 transition-colors">
                    <flux:icon.paper-clip class="w-3.5 h-3.5"/>
                    <span>{{ $attachmentCount }}</span>
                </div>
                @endif
                @if($linkCount > 0)
                <div class="flex items-center gap-1 text-[11px] font-medium hover:text-indigo-500 transition-colors">
                    <flux:icon.link class="w-3.5 h-3.5"/>
                    <span>{{ $linkCount }}</span>
                </div>
                @endif
            </div>
            
            <div class="flex -space-x-2 shrink-0 justify-end">
                @forelse($project['assignees'] as $assignee)
                    <div class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/50 dark:to-purple-900/50 ring-2 ring-white dark:ring-zinc-900 flex items-center justify-center text-[10px] font-bold text-indigo-700 dark:text-indigo-300 shadow-sm overflow-hidden transform transition-transform hover:scale-110 hover:z-10" title="{{ $assignee['name'] }}">
                        @if(!empty($assignee['avatar']))
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($assignee['avatar']) }}" class="w-full h-full object-cover">
                        @else
                            {{ substr($assignee['name'], 0, 1) }}
                        @endif
                    </div>
                @empty
                    <div class="w-6 h-6 rounded-full bg-zinc-100 dark:bg-zinc-800 ring-2 ring-white dark:ring-zinc-900 flex items-center justify-center shadow-sm">
                        <flux:icon.user class="w-3 h-3 text-zinc-400" />
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endforeach
