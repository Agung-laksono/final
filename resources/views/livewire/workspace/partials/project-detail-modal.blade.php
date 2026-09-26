    <flux:modal wire:model="showProjectModal" class="w-full max-w-5xl p-0 max-sm:!m-0 max-sm:!w-[100vw] max-sm:!max-w-[100vw] max-sm:!h-[100dvh] max-sm:!max-h-[100dvh] max-sm:!rounded-none kanban-modal">
        
        {{-- SKELETON UI --}}
        <div wire:loading wire:target="openModal" class="w-full">
            <div class="flex flex-col md:flex-row gap-0 h-[85vh] max-sm:h-[100dvh] p-6">
                {{-- Left column --}}
                <div class="flex-1 space-y-10 pr-6 pt-6 animate-pulse">
                    <div>
                        <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-3/4 mb-4"></div>
                        <div class="flex gap-2">
                            <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-24"></div>
                            <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-24"></div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/4 mb-4"></div>
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-full"></div>
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-5/6"></div>
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-4/6"></div>
                    </div>
                    <div class="space-y-4 pt-8">
                        <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/4"></div>
                        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-full"></div>
                    </div>
                </div>
                {{-- Right column --}}
                <div class="shrink-0 w-full md:w-[156px] space-y-8 pt-6 animate-pulse hidden md:block">
                    <div class="space-y-3">
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-24 mb-4"></div>
                        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-full"></div>
                        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-full"></div>
                        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-full"></div>
                        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-full"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ACTUAL CONTENT --}}
        <div wire:loading.remove wire:target="openModal" class="w-full">
            @if($selectedProject)
            <div x-data="{ sidebarOpen: true, isFullscreen: false }" 
                 x-init="$watch('isFullscreen', val => document.body.classList.toggle('kanban-fullscreen', val)); if(isFullscreen) document.body.classList.add('kanban-fullscreen');"
                 class="flex flex-col md:flex-row gap-0 transition-all duration-500 ease-out" 
                 x-bind:class="isFullscreen ? '' : 'max-sm:h-[100dvh]'">
                
<style>
    body.kanban-fullscreen dialog:has(.kanban-modal),
    body.kanban-fullscreen dialog.kanban-modal,
    body.kanban-fullscreen .kanban-modal {
        max-width: 100vw !important;
        width: 100vw !important;
        max-height: 100dvh !important;
        height: 100dvh !important;
        margin: 0 !important;
        padding: 0 !important;
        border-radius: 0 !important;
        transform: none !important;
    }
</style>

                {{-- Left Column: Main Content --}}
                <div class="flex-1 space-y-12 overflow-y-auto max-sm:max-h-none pr-6 custom-scrollbar" x-bind:class="isFullscreen ? 'max-h-[100dvh]' : 'max-h-[85vh]'">
                    
                    {{-- 1. Header (Title & List) --}}
                    <div class="group relative">
                        <div class="flex items-start gap-4">
                            {{-- Fullscreen Toggle Button --}}
                            <button @click="isFullscreen = !isFullscreen" class="absolute -left-12 top-0.5 p-2 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-zinc-200/50 dark:border-zinc-700/50 hover:bg-white dark:hover:bg-zinc-700 hover:scale-110 hover:shadow-sm text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 rounded-xl transition-all duration-300 opacity-0 group-hover:opacity-100" title="Toggle Fullscreen">
                                <flux:icon.arrows-pointing-out x-show="!isFullscreen" class="w-4 h-4"/>
                                <flux:icon.arrows-pointing-in x-show="isFullscreen" class="w-4 h-4" style="display: none;"/>
                            </button>

                            <div class="mt-1 p-2 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl border border-indigo-100 dark:border-indigo-500/20 shadow-sm">
                                <flux:icon.computer-desktop class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="flex-1">
                                <h2 class="text-2xl font-extrabold text-zinc-900 dark:text-white mb-1.5 tracking-tight leading-tight">
                                    {{ $selectedProject['title'] }}
                                </h2>
                                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5">
                                    <span>in list</span>
                                    <span class="px-2 py-0.5 bg-zinc-100 dark:bg-zinc-800/80 rounded-md text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-sm">{{ collect($this->columns)->firstWhere('id', $selectedProject['workspace_column_id'])['title'] ?? 'Unknown' }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Metadata (Members, Labels, Due Date) --}}
                    <div class="ml-14 flex flex-wrap gap-10">
                        {{-- Members --}}
                        <div class="space-y-3">
                            <h3 class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">Assignees</h3>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @forelse($selectedProject['assignees'] ?? [] as $assignee)
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 border-2 border-white dark:border-zinc-900 shadow-sm flex items-center justify-center text-sm font-bold text-indigo-700 dark:text-indigo-300 hover:-translate-y-1 hover:shadow-md transition-all duration-300 cursor-pointer overflow-hidden" title="{{ $assignee['name'] }}">
                                        @if(!empty($assignee['avatar']))
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($assignee['avatar']) }}" class="w-full h-full object-cover">
                                        @else
                                            {{ substr($assignee['name'], 0, 1) }}
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-sm text-zinc-400 italic">Unassigned</span>
                                @endforelse
                                <flux:dropdown>
                                    <button class="w-9 h-9 rounded-full bg-zinc-100/80 hover:bg-zinc-200 dark:bg-zinc-800/80 dark:hover:bg-zinc-700 border-2 border-dashed border-zinc-300 dark:border-zinc-600 flex items-center justify-center text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 hover:scale-105 hover:shadow-sm transition-all duration-300">
                                        <flux:icon.plus class="w-4 h-4" />
                                    </button>
                                    <flux:menu class="max-h-64 overflow-y-auto w-64">
                                        <flux:menu.heading>Assign Members</flux:menu.heading>
                                        @foreach($workspace->users as $user)
                                            <flux:menu.checkbox wire:key="assignee-{{ $user->id }}" wire:click="toggleAssignee({{ $user->id }})" :checked="collect($selectedProject['assignees'] ?? [])->pluck('id')->contains($user->id)">
                                                {{ $user->name }}
                                            </flux:menu.checkbox>
                                        @endforeach
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </div>

                        {{-- Labels --}}
                        <div class="space-y-3">
                            <h3 class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">Labels</h3>
                            <div class="flex items-center gap-2 flex-wrap">
                                @forelse($selectedProject['labels'] ?? [] as $label)
                                    <span class="px-3 py-1.5 text-xs font-bold rounded-lg text-white shadow-sm hover:-translate-y-0.5 hover:shadow transition-all duration-300 cursor-pointer" style="background-color: {{ $label['color'] ?? '#6366f1' }}">
                                        {{ $label['name'] }}
                                    </span>
                                @empty
                                    <span class="px-3 py-1.5 text-xs font-medium rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 shadow-sm">
                                        None
                                    </span>
                                @endforelse
                                <flux:dropdown>
                                    <button class="h-8 px-2.5 rounded-lg bg-zinc-100/80 hover:bg-zinc-200 dark:bg-zinc-800/80 dark:hover:bg-zinc-700 border-2 border-dashed border-zinc-300 dark:border-zinc-600 flex items-center justify-center text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 hover:scale-105 hover:shadow-sm transition-all duration-300">
                                        <flux:icon.plus class="w-4 h-4" />
                                    </button>
                                    <flux:menu class="max-h-64 overflow-y-auto w-64">
                                        <flux:menu.heading>Assign Labels</flux:menu.heading>
                                        @foreach($availableLabels as $label)
                                            <flux:menu.checkbox wire:click="toggleLabel({{ $label['id'] }})" :checked="collect($selectedProject['labels'] ?? [])->pluck('id')->contains($label['id'])">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-3 h-3 rounded-full shadow-sm" style="background-color: {{ $label['color'] }}"></div>
                                                    {{ $label['name'] }}
                                                </div>
                                            </flux:menu.checkbox>
                                        @endforeach
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </div>

                        {{-- Priority --}}
                        <div class="space-y-3">
                            <h3 class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">Priority</h3>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @php
                                    $currentPriority = $selectedProject['priority'] ?? 'normal';
                                    $pConfig = match($currentPriority) {
                                        'critical' => ['label' => 'Critical', 'color' => '#ef4444'],
                                        'high'     => ['label' => 'High', 'color' => '#f97316'],
                                        'normal'   => ['label' => 'Normal', 'color' => '#64748b'],
                                        'low'      => ['label' => 'Low', 'color' => '#94a3b8'],
                                        default    => ['label' => 'Normal', 'color' => '#64748b'],
                                    };
                                @endphp
                                
                                <flux:dropdown>
                                    <button class="flex items-center gap-2 bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 px-3 py-1.5 rounded-lg hover:bg-zinc-200/80 dark:hover:bg-zinc-700 hover:shadow-sm transition-all duration-300 cursor-pointer group shadow-sm">
                                        <div class="w-3 h-3 rounded-full shadow-inner border border-white/20" style="background-color: {{ $pConfig['color'] }}"></div>
                                        <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $pConfig['label'] }}</span>
                                        <flux:icon.chevron-down class="w-3 h-3 text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors ml-1" />
                                    </button>
                                    
                                    <flux:menu>
                                        <flux:menu.heading>Ubah Prioritas</flux:menu.heading>
                                        <flux:menu.item wire:click="updateTaskField({{ $selectedProject['id'] }}, 'priority', 'critical')">
                                            <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-red-500"></div> Critical</div>
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="updateTaskField({{ $selectedProject['id'] }}, 'priority', 'high')">
                                            <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div> High</div>
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="updateTaskField({{ $selectedProject['id'] }}, 'priority', 'normal')">
                                            <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-slate-500"></div> Normal</div>
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="updateTaskField({{ $selectedProject['id'] }}, 'priority', 'low')">
                                            <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-slate-400"></div> Low</div>
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </div>

                        {{-- Due Date --}}
                        <div class="space-y-3">
                            <h3 class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">Due Date</h3>
                            <div class="flex items-center gap-2 bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 px-3 py-1.5 rounded-lg hover:bg-zinc-200/80 dark:hover:bg-zinc-700 hover:shadow-sm transition-all duration-300 cursor-pointer group shadow-sm">
                                <flux:checkbox class="transition-transform group-hover:scale-110" />
                                <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">
                                    {{ $selectedProject['due_date'] ? \Carbon\Carbon::parse($selectedProject['due_date'])->format('M d, Y') : 'Set date' }}
                                </span>
                                @if($selectedProject['due_date'])
                                    @if(\Carbon\Carbon::parse($selectedProject['due_date'])->isPast())
                                        <span class="text-[10px] font-bold text-red-700 bg-red-100 dark:bg-red-500/20 dark:text-red-400 px-2 py-0.5 rounded-md uppercase tracking-wider">Overdue</span>
                                    @else
                                        <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 dark:bg-emerald-500/20 dark:text-emerald-400 px-2 py-0.5 rounded-md uppercase tracking-wider">Due</span>
                                    @endif
                                @endif
                                <flux:icon.chevron-down class="w-3 h-3 text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors ml-1" />
                            </div>
                        </div>
                    </div>

                    {{-- 3. Description --}}
                    <div class="flex items-start gap-4">
                        <div class="mt-1 p-2 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 shadow-sm">
                            <flux:icon.bars-3-bottom-left class="w-5 h-5" />
                        </div>
                        <div class="flex-1 space-y-3">
                            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Description</h3>
                            <div class="bg-zinc-50/50 dark:bg-zinc-900/50 hover:bg-white dark:hover:bg-zinc-800/80 p-5 rounded-xl cursor-pointer border border-zinc-200/80 dark:border-zinc-700/80 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-md transition-all duration-300 min-h-[100px] group shadow-sm">
                                <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed group-hover:text-zinc-900 dark:group-hover:text-zinc-100 transition-colors">
                                    {{ $selectedProject['description'] ?? 'Add a more detailed description...' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Checklist --}}
                    <div class="flex items-start gap-4">
                        <div class="mt-1 p-2 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 shadow-sm">
                            <flux:icon.check-circle class="w-5 h-5" />
                        </div>
                        <div class="flex-1 space-y-5">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Checklist</h3>
                                <div class="flex gap-2">
                                    <flux:button variant="subtle" size="sm" class="bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 border-none rounded-lg transition-all shadow-sm">Hide completed</flux:button>
                                    <flux:button variant="subtle" size="sm" class="bg-zinc-100 hover:bg-red-50 dark:bg-zinc-800 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400 border-none rounded-lg transition-all shadow-sm">Delete</flux:button>
                                </div>
                            </div>
                            
                            @php
                                $total = count($selectedProject['subtasks'] ?? []);
                                $completed = collect($selectedProject['subtasks'] ?? [])->where('is_completed', true)->count();
                                $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
                            @endphp
                            
                            <div class="flex items-center gap-4 mb-4">
                                <span class="text-xs font-bold {{ $progress == 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }} w-10 text-right">{{ $progress }}%</span>
                                <div class="flex-1 bg-zinc-100 dark:bg-zinc-800 rounded-full h-2.5 shadow-inner overflow-hidden border border-zinc-200 dark:border-zinc-700">
                                    <div class="{{ $progress == 100 ? 'bg-gradient-to-r from-emerald-400 to-emerald-500' : 'bg-gradient-to-r from-indigo-500 to-purple-500' }} h-full rounded-full transition-all duration-500 ease-out shadow-sm" style="width: {{ $progress }}%"></div>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                @foreach($selectedProject['subtasks'] ?? [] as $subtask)
                                    <div class="flex items-start gap-3 group p-2 -mx-2 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                        <div class="pt-0.5">
                                            <flux:checkbox wire:click="toggleSubtask({{ $subtask['id'] }})" :checked="$subtask['is_completed']" class="transition-transform group-hover:scale-110" />
                                        </div>
                                        <div class="flex-1">
                                            <span class="text-sm {{ $subtask['is_completed'] ? 'line-through text-zinc-400 dark:text-zinc-500' : 'font-medium text-zinc-800 dark:text-zinc-200' }} transition-all">
                                                {{ $subtask['title'] }}
                                            </span>
                                            
                                            @if($subtask['requires_input'])
                                                <div class="mt-3" x-data="{ inputValue: '{{ $subtask['input_value'] ?? '' }}' }">
                                                    <div class="flex items-center gap-2 max-w-sm">
                                                        <flux:input x-model="inputValue" size="sm" placeholder="Enter value..." class="w-full !rounded-lg" />
                                                        <flux:button size="sm" wire:click="saveSubtaskValue({{ $subtask['id'] }}, inputValue)" class="!rounded-lg whitespace-nowrap">Save</flux:button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                                
                                <div class="pt-2">
                                    <flux:button variant="subtle" size="sm" class="bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 border-none rounded-lg transition-all font-medium shadow-sm">Add an item</flux:button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 5. Attachments --}}
                    @if(!empty($selectedProject['attachments']))
                    <div class="flex items-start gap-4">
                        <div class="mt-1 p-2 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 shadow-sm">
                            <flux:icon.paper-clip class="w-5 h-5" />
                        </div>
                        <div class="flex-1 space-y-4">
                            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Attachments</h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                @foreach($selectedProject['attachments'] as $attachment)
                                    <div class="group flex flex-col bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 hover:border-indigo-300 dark:hover:border-indigo-500/50 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer">
                                        <div class="h-24 bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center relative overflow-hidden">
                                            <div class="absolute inset-0 bg-gradient-to-b from-transparent to-black/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                            @if(str_contains($attachment['file_type'], 'image'))
                                                <img loading="lazy" src="{{ asset('storage/' . $attachment['file_path']) }}" alt="{{ $attachment['file_name'] }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                                            @elseif(str_contains($attachment['file_type'], 'pdf'))
                                                <flux:icon.document-text class="w-8 h-8 text-zinc-400 group-hover:scale-110 group-hover:text-red-500 transition-all duration-300" />
                                            @else
                                                <flux:icon.document class="w-8 h-8 text-zinc-400 group-hover:scale-110 group-hover:text-zinc-600 transition-all duration-300" />
                                            @endif
                                        </div>
                                        <div class="p-3 border-t border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900/50">
                                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate w-full group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ $attachment['file_name'] }}</p>
                                            <p class="text-[10px] text-zinc-500 mt-0.5 uppercase tracking-wider font-medium">
                                                Added {{ \Carbon\Carbon::parse($attachment['created_at'])->shortAbsoluteDiffForHumans() }}
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="pt-2">
                                <flux:button variant="subtle" size="sm" class="bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 border-none rounded-lg transition-all font-medium shadow-sm">Add an attachment</flux:button>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- 6. Activity & Comments --}}
                    @include('livewire.workspace.partials.project-activity')
                </div>

                {{-- Right Column: Sidebar Actions --}}
                <div class="shrink-0 space-y-8 pt-12 transition-all duration-500 ease-out relative pl-6 -my-6 py-6 rounded-r-2xl flex flex-col" x-bind:class="sidebarOpen ? 'w-full md:w-[180px]' : 'w-full md:w-[84px]'">
                    {{-- Sidebar Toggle Button --}}
                    <div class="absolute top-4 -left-3.5 hidden md:flex justify-end z-10">
                        <button @click="sidebarOpen = !sidebarOpen" class="flex items-center justify-center p-1.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow hover:shadow-md rounded-full transition-all duration-300 hover:scale-110" title="Toggle Sidebar">
                            <flux:icon.chevron-right x-show="sidebarOpen" class="w-3.5 h-3.5"/>
                            <flux:icon.chevron-left x-show="!sidebarOpen" class="w-3.5 h-3.5" style="display: none;"/>
                        </button>
                    </div>

                    {{-- Add to card --}}
                    <div>
                        <h4 class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 mb-3 truncate uppercase tracking-widest" x-show="sidebarOpen">Add to card</h4>
                        <div class="flex flex-col gap-2.5">
                            <flux:dropdown class="w-full">
                                <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="user" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Members</span>
                                </flux:button>
                                <flux:menu class="max-h-64 overflow-y-auto w-64">
                                    <flux:menu.heading>Assign Members</flux:menu.heading>
                                    @foreach($workspace->users as $user)
                                        <flux:menu.checkbox wire:key="assignee-sidebar-{{ $user->id }}" wire:click="toggleAssignee({{ $user->id }})" :checked="collect($selectedProject['assignees'] ?? [])->pluck('id')->contains($user->id)">
                                            {{ $user->name }}
                                        </flux:menu.checkbox>
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>

                            <flux:dropdown class="w-full">
                                <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="tag" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Labels</span>
                                </flux:button>
                                <flux:menu class="max-h-64 overflow-y-auto w-64">
                                    <flux:menu.heading>Assign Labels</flux:menu.heading>
                                    @foreach($availableLabels as $label)
                                        <flux:menu.checkbox wire:click="toggleLabel({{ $label['id'] }})" :checked="collect($selectedProject['labels'] ?? [])->pluck('id')->contains($label['id'])">
                                            <div class="flex items-center gap-2">
                                                <div class="w-3 h-3 rounded-full" style="background-color: {{ $label['color'] }}"></div>
                                                {{ $label['name'] }}
                                            </div>
                                        </flux:menu.checkbox>
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                            
                            <div class="w-full">
                                <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="check-circle" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Checklist</span>
                                </flux:button>
                            </div>
                            
                            <div class="relative w-full group">
                                <style>
                                    .hide-calendar-icon::-webkit-calendar-picker-indicator {
                                        display: none;
                                        -webkit-appearance: none;
                                    }
                                </style>
                                <div x-show="sidebarOpen" class="relative">
                                    <input type="date" 
                                           wire:change="updateProjectField('due_date', $event.target.value)" 
                                           value="{{ $selectedProject['due_date'] ? \Carbon\Carbon::parse($selectedProject['due_date'])->format('Y-m-d') : '' }}" 
                                           class="hide-calendar-icon w-full h-10 pl-10 pr-3 text-sm text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] cursor-pointer outline-none focus:ring-2 focus:ring-indigo-500/20" />
                                    <div class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors">
                                        <flux:icon.calendar-days class="w-5 h-5" />
                                    </div>
                                </div>
                                
                                <flux:button x-show="!sidebarOpen" variant="subtle" class="w-full justify-center px-0 h-10 w-10 text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="calendar-days" title="{{ $selectedProject['due_date'] ? \Carbon\Carbon::parse($selectedProject['due_date'])->format('Y-m-d') : 'Set Due Date' }}"></flux:button>
                            </div>

                            <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="paper-clip" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                <span x-show="sidebarOpen">Attachment</span>
                            </flux:button>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div>
                        <h4 class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 mb-3 truncate uppercase tracking-widest" x-show="sidebarOpen">Actions</h4>
                        <div class="flex flex-col gap-2.5">
                            <div class="w-full">
                                <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="arrow-right" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Move</span>
                                </flux:button>
                            </div>
                            <div class="w-full">
                                <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="document-duplicate" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Copy</span>
                                </flux:button>
                            </div>
                            <div class="w-full">
                                <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="archive-box" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Archive</span>
                                </flux:button>
                            </div>
                            <div class="w-full">
                                <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="share" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Share</span>
                                </flux:button>
                            </div>
                        </div>
                    </div>

                    {{-- Desktop Sticky Log Button --}}
                    <div class="mt-auto pt-8 sticky bottom-0 pb-6 hidden md:block bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md z-10 w-full">
                        <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="clock" x-on:click="$flux.modal('project-log').show()" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                            <span x-show="sidebarOpen">View Logs</span>
                        </flux:button>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </flux:modal>

    @include('livewire.workspace.partials.project-log-modal')
