<?php

use App\Models\User;
use Modules\Marketing\Models\MarketingProject;
use Modules\Marketing\Models\MarketingSubtask;
use Modules\Marketing\Models\MarketingComment;
use Modules\Marketing\Models\MarketingAttachment;
use Modules\Marketing\Models\MarketingTimeLog;
use Modules\Marketing\Models\MarketingActivity;
use Illuminate\Support\Carbon;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component {
    use WithFileUploads;

    public array $projects = [];
    
    public array $columns = [
        'ide_backlog' => ['title' => 'Ide/Backlog', 'color' => 'gray'],
        'shooting' => ['title' => 'Shooting', 'color' => 'blue'],
        'editing' => ['title' => 'Editing', 'color' => 'orange'],
        'ready' => ['title' => 'Ready', 'color' => 'yellow'],
        'publish' => ['title' => 'Publish', 'color' => 'green'],
        'analytic' => ['title' => 'Analytic', 'color' => 'purple'],
    ];

    public $selectedProject = null;
    public string $newSubtaskTitle = '';
    public bool $showProjectModal = false;

    // State for new items
    public string $newComment = '';
    public $attachmentFile = null;
    public string $timeLogHours = '';
    public string $timeLogMinutes = '';
    public string $timeLogDescription = '';

    public ?string $addingTaskToColumn = null;
    public string $newTaskTitle = '';

    public function mount() {
        $this->loadProjects();
    }

    public function loadProjects() {
        $this->projects = MarketingProject::with([
            'subtasks', 'labels', 'assignees', 'comments.user', 'attachments.user', 'activities.user', 'timeLogs.user'
        ])
            ->orderBy('position', 'asc')
            ->get()->toArray();
    }

    public function recordActivity($projectId, $description) {
        MarketingActivity::create([
            'marketing_project_id' => $projectId,
            'user_id' => auth()->id(),
            'action' => 'update',
            'description' => $description,
        ]);
    }

    public function showAddTask($status) {
        $this->addingTaskToColumn = $status;
        $this->newTaskTitle = '';
    }

    public function cancelAddTask() {
        $this->addingTaskToColumn = null;
        $this->newTaskTitle = '';
    }

    public function addTask() {
        if (empty(trim($this->newTaskTitle)) || !$this->addingTaskToColumn) return;

        $maxPosition = collect($this->projects)
            ->where('status', $this->addingTaskToColumn)
            ->max('position');
            
        $maxPosition = $maxPosition ?? -1;

        $project = MarketingProject::create([
            'title' => trim($this->newTaskTitle),
            'status' => $this->addingTaskToColumn,
            'position' => $maxPosition + 1,
            'platform' => 'General',
        ]);

        $this->recordActivity($project->id, 'Membuat tugas baru');

        $this->addingTaskToColumn = null;
        $this->newTaskTitle = '';
        $this->loadProjects();
    }

    public function reorder($orderedIds, $newStatus) {
        foreach ($orderedIds as $index => $id) {
            $project = MarketingProject::find($id);
            if ($project && $project->status !== $newStatus) {
                $this->recordActivity($id, 'Memindahkan tugas ke kolom ' . ($this->columns[$newStatus]['title'] ?? $newStatus));
            }
            
            if ($project) {
                $project->update([
                    'status' => $newStatus,
                    'position' => $index
                ]);
            }
        }
        $this->loadProjects();
    }

    public function move($projectId, $newStatus) {
        $project = MarketingProject::find($projectId);
        if ($project && $project->status !== $newStatus) {
            $this->recordActivity($projectId, 'Memindahkan tugas ke kolom ' . ($this->columns[$newStatus]['title'] ?? $newStatus));
            $project->update(['status' => $newStatus]);
        }
        
        $this->loadProjects();
        
        if ($this->selectedProject && $this->selectedProject['id'] == $projectId) {
            $this->selectedProject = collect($this->projects)->firstWhere('id', $projectId);
        }
    }

    public function openModal($projectId) {
        $this->selectedProject = collect($this->projects)->firstWhere('id', $projectId);
        $this->newSubtaskTitle = '';
        $this->showProjectModal = true;
    }

    public function saveSubtaskValue($subtaskId, $value) {
        $subtask = MarketingSubtask::find($subtaskId);
        if ($subtask) {
            $subtask->update(['input_value' => $value]);
            if (!empty(trim($value)) && $subtask->requires_input) {
                $subtask->is_completed = true;
                $subtask->save();
            }
            $this->recordActivity($subtask->marketing_project_id, "Memperbarui input subtask: {$subtask->title}");
            $this->loadProjects();
            if ($this->selectedProject) {
                $this->selectedProject = collect($this->projects)->firstWhere('id', $this->selectedProject['id']);
            }
        }
    }

    public function addComment() {
        if (empty(trim($this->newComment)) || !$this->selectedProject) return;
        
        MarketingComment::create([
            'marketing_project_id' => $this->selectedProject['id'],
            'user_id' => auth()->id(),
            'content' => $this->newComment,
        ]);
        
        $this->recordActivity($this->selectedProject['id'], 'Menambahkan komentar baru');
        
        $this->newComment = '';
        $this->loadProjects();
        $this->selectedProject = collect($this->projects)->firstWhere('id', $this->selectedProject['id']);
    }

    public function uploadAttachment() {
        if (!$this->attachmentFile || !$this->selectedProject) return;
        
        $path = $this->attachmentFile->store('marketing_attachments', 'public');
        
        MarketingAttachment::create([
            'marketing_project_id' => $this->selectedProject['id'],
            'user_id' => auth()->id(),
            'file_name' => $this->attachmentFile->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $this->attachmentFile->getClientMimeType(),
            'file_size' => $this->attachmentFile->getSize(),
        ]);
        
        $this->recordActivity($this->selectedProject['id'], "Mengunggah lampiran: " . $this->attachmentFile->getClientOriginalName());
        
        $this->attachmentFile = null;
        $this->loadProjects();
        $this->selectedProject = collect($this->projects)->firstWhere('id', $this->selectedProject['id']);
    }

    public function addTimeLog() {
        if (!$this->selectedProject) return;
        
        $hours = (int)$this->timeLogHours;
        $minutes = (int)$this->timeLogMinutes;
        
        if ($hours === 0 && $minutes === 0) return;
        
        MarketingTimeLog::create([
            'marketing_project_id' => $this->selectedProject['id'],
            'user_id' => auth()->id(),
            'hours' => $hours,
            'minutes' => $minutes,
            'description' => $this->timeLogDescription,
        ]);
        
        $this->recordActivity($this->selectedProject['id'], "Mencatat waktu: {$hours}j {$minutes}m");
        
        $this->timeLogHours = '';
        $this->timeLogMinutes = '';
        $this->timeLogDescription = '';
        $this->loadProjects();
        $this->selectedProject = collect($this->projects)->firstWhere('id', $this->selectedProject['id']);
    }

    public function formatTime($totalMinutes) {
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        
        if ($hours > 0 && $minutes > 0) return "{$hours}j {$minutes}m";
        if ($hours > 0) return "{$hours} jam";
        return "{$minutes} menit";
    }

    public function closeModal() {
        $this->showProjectModal = false;
        $this->selectedProject = null;
        $this->newComment = '';
        $this->attachmentFile = null;
        $this->timeLogHours = '';
        $this->timeLogMinutes = '';
        $this->timeLogDescription = '';
    }

    public function toggleSubtask($subtaskId) {
        $subtask = MarketingSubtask::find($subtaskId);
        if ($subtask) {
            $subtask->update(['is_completed' => !$subtask->is_completed]);
            
            $action = $subtask->is_completed ? 'Menyelesaikan' : 'Membatalkan penyelesaian';
            $this->recordActivity($subtask->marketing_project_id, "$action subtask: {$subtask->title}");
            
            $this->loadProjects();
            if ($this->selectedProject) {
                $this->selectedProject = collect($this->projects)->firstWhere('id', $this->selectedProject['id']);
            }
        }
    }

    public function addSubtask() {
        if (!$this->selectedProject || empty(trim($this->newSubtaskTitle))) return;
        
        $subtask = MarketingSubtask::create([
            'marketing_project_id' => $this->selectedProject['id'],
            'title' => trim($this->newSubtaskTitle),
            'is_completed' => false,
            'requires_input' => false
        ]);
        
        $this->recordActivity($this->selectedProject['id'], "Menambahkan subtask: {$subtask->title}");
        
        $this->loadProjects();
        $this->selectedProject = collect($this->projects)->firstWhere('id', $this->selectedProject['id']);
        $this->newSubtaskTitle = '';
    }

    public function deleteSubtask($subtaskId) {
        $subtask = MarketingSubtask::find($subtaskId);
        if ($subtask) {
            $projectId = $subtask->marketing_project_id;
            $title = $subtask->title;
            $subtask->delete();
            
            $this->recordActivity($projectId, "Menghapus subtask: $title");
            
            $this->loadProjects();
            if ($this->selectedProject) {
                $this->selectedProject = collect($this->projects)->firstWhere('id', $this->selectedProject['id']);
            }
        }
    }
};
?>

<div class="h-full w-full flex flex-col space-y-4">
    <div class="flex items-center justify-between px-2">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Marketing Project Board</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Kelola alur kerja konten promosi Anda di sini.</p>
        </div>
        <flux:button variant="primary" icon="plus">New Task</flux:button>
    </div>

    {{-- Kanban Board Area --}}
    <div class="flex-1 overflow-x-auto pb-4">
        <div class="flex gap-4 min-w-max h-full items-start px-2">
            @foreach($columns as $statusKey => $columnData)
                @php
                    $columnProjects = collect($projects)->where('status', $statusKey);
                    
                    $colorClasses = match($columnData['color']) {
                        'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 ring-gray-600/20',
                        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 ring-blue-600/20',
                        'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300 ring-orange-600/20',
                        'yellow' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300 ring-yellow-600/20',
                        'green' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 ring-green-600/20',
                        'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 ring-purple-600/20',
                        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 ring-zinc-600/20'
                    };
                @endphp
                <div class="w-[300px] flex-shrink-0 flex flex-col bg-zinc-50 dark:bg-zinc-800/50 rounded-xl max-h-full">
                    {{-- Column Header --}}
                    <div class="p-3 border-b border-zinc-200 dark:border-zinc-700/50 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $colorClasses }}">
                                {{ $columnData['title'] }}
                            </span>
                            <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ $columnProjects->count() }}</span>
                        </div>
                    </div>
                    
                    {{-- Column Body --}}
                    <div class="p-3 flex-1 overflow-y-auto space-y-3 custom-scrollbar rounded-b-xl transition-all duration-300 border-2 border-transparent"
                         id="kanban-col-{{ $statusKey }}"
                         data-status="{{ $statusKey }}"
                         x-init="
                            Sortable.create($el, {
                                group: 'kanban',
                                animation: 150,
                                ghostClass: 'opacity-40',
                                dragClass: 'rotate-2',
                                onEnd: (e) => {
                                    let targetEl = e.to;
                                    let newStatus = targetEl.getAttribute('data-status');
                                    let orderedIds = Array.from(targetEl.children)
                                        .filter(el => el.hasAttribute('data-id'))
                                        .map(el => el.getAttribute('data-id'));
                                    
                                    $wire.reorder(orderedIds, newStatus);
                                }
                            })
                         ">
                        @foreach($columnProjects as $project)
                            @php
                                $totalSub = isset($project['subtasks']) ? count($project['subtasks']) : 0;
                                $completedSub = isset($project['subtasks']) ? collect($project['subtasks'])->where('is_completed', true)->count() : 0;
                            @endphp
                            <div wire:click="openModal({{ $project['id'] }})" 
                                 data-id="{{ $project['id'] }}"
                                 class="bg-white dark:bg-zinc-900 rounded-lg p-3 shadow-sm border border-zinc-200 dark:border-zinc-800 group relative cursor-grab active:cursor-grabbing hover:border-indigo-300 dark:hover:border-indigo-700 transition-all duration-200 origin-center">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100 leading-tight pr-6">{{ $project['title'] }}</h4>
                                    
                                    <div class="absolute top-2 right-2" @click.stop>
                                        <flux:dropdown>
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
                                </div>
                                
                                <div class="flex flex-wrap gap-1 mb-3">
                                    <span class="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-400/10 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 dark:text-blue-400 ring-1 ring-inset ring-blue-700/10 dark:ring-blue-400/20">{{ $project['platform'] }}</span>
                                </div>
                                
                                @if(isset($project['reach']))
                                <div class="text-xs text-green-600 dark:text-green-400 font-medium mb-3 flex items-center gap-1">
                                    <flux:icon.chart-bar class="w-3 h-3"/> {{ $project['reach'] }}
                                </div>
                                @endif
                                
                                @if($totalSub > 0)
                                <div class="flex items-center gap-1 text-[11px] text-zinc-500 dark:text-zinc-400 font-medium mb-3">
                                    <flux:icon.check-circle class="w-3.5 h-3.5 {{ $completedSub == $totalSub ? 'text-green-500' : '' }}"/>
                                    <span class="{{ $completedSub == $totalSub ? 'text-green-600 dark:text-green-500' : '' }}">{{ $completedSub }}/{{ $totalSub }}</span>
                                </div>
                                @endif

                                <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400 mt-auto">
                                    <div class="flex items-center gap-1 text-xs">
                                        <flux:icon.calendar class="w-3.5 h-3.5"/>
                                        <span>{{ $project['due_date'] ? \Carbon\Carbon::parse($project['due_date'])->format('d M') : '-' }}</span>
                                    </div>
                                    @php
                                        $firstAssignee = !empty($project['assignees']) ? $project['assignees'][0]['name'] : 'Unassigned';
                                    @endphp
                                    <div class="w-6 h-6 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-[10px] font-bold text-zinc-600 dark:text-zinc-300 ring-2 ring-white dark:ring-zinc-900" title="Assignee: {{ $firstAssignee }}">
                                        {{ substr($firstAssignee, 0, 1) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        
                        {{-- Add task inline form or button --}}
                        @if($addingTaskToColumn === $statusKey)
                            <div class="bg-white dark:bg-zinc-800 p-3 rounded-lg border border-indigo-200 dark:border-indigo-800/30 shadow-sm space-y-2">
                                <flux:input wire:model="newTaskTitle" placeholder="Judul task baru..." wire:keydown.enter="addTask" />
                                <div class="flex items-center gap-2 justify-end mt-2">
                                    <flux:button wire:click="cancelAddTask" size="xs" variant="ghost">Batal</flux:button>
                                    <flux:button wire:click="addTask" size="xs" variant="primary">Tambah</flux:button>
                                </div>
                            </div>
                        @else
                            <button wire:click="showAddTask('{{ $statusKey }}')" class="w-full py-2 flex items-center justify-center gap-1 text-sm text-zinc-500 dark:text-zinc-400 hover:bg-zinc-200/50 dark:hover:bg-zinc-700/50 rounded-md transition-colors border border-dashed border-transparent hover:border-zinc-300 dark:hover:border-zinc-600">
                                <flux:icon.plus class="w-4 h-4"/>
                                <span>Add task</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Project Detail Modal --}}
    <flux:modal wire:model="showProjectModal" class="w-full max-w-4xl min-h-[600px] flex flex-col">
        @if($selectedProject)
            <div class="flex flex-col h-full" x-data="{ activeTab: 'details' }">
                {{-- Header --}}
                <div class="flex justify-between items-start mb-6 shrink-0">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-400/10 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-400 ring-1 ring-inset ring-blue-700/10 dark:ring-blue-400/20">
                                {{ $selectedProject['platform'] }}
                            </span>
                            <span class="text-xs text-zinc-500">
                                Tenggat: {{ $selectedProject['due_date'] ? \Carbon\Carbon::parse($selectedProject['due_date'])->format('d F Y') : '-' }}
                            </span>
                        </div>
                        <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $selectedProject['title'] }}
                        </h2>
                    </div>
                </div>

                {{-- Tabs Header --}}
                <div class="flex gap-6 border-b border-zinc-200 dark:border-zinc-700 shrink-0">
                    <button @click="activeTab = 'details'" :class="activeTab === 'details' ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 border-b-2 border-transparent'" class="pb-2 text-sm font-medium transition-colors">Detail & Subtasks</button>
                    <button @click="activeTab = 'comments'" :class="activeTab === 'comments' ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 border-b-2 border-transparent'" class="pb-2 text-sm font-medium transition-colors">Komentar ({{ count($selectedProject['comments'] ?? []) }})</button>
                    <button @click="activeTab = 'attachments'" :class="activeTab === 'attachments' ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 border-b-2 border-transparent'" class="pb-2 text-sm font-medium transition-colors">Lampiran ({{ count($selectedProject['attachments'] ?? []) }})</button>
                    <button @click="activeTab = 'time'" :class="activeTab === 'time' ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 border-b-2 border-transparent'" class="pb-2 text-sm font-medium transition-colors">Waktu & Biaya</button>
                    <button @click="activeTab = 'activity'" :class="activeTab === 'activity' ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 border-b-2 border-transparent'" class="pb-2 text-sm font-medium transition-colors">Aktivitas</button>
                </div>

                {{-- Tabs Content --}}
                <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 pt-6 min-h-[400px]">
                    
                    {{-- Tab 1: Details & Subtasks --}}
                    <div x-show="activeTab === 'details'" class="space-y-6">
                        <div class="grid grid-cols-3 gap-6">
                            <div class="col-span-2 space-y-6">
                                {{-- Description --}}
                                <div>
                                    <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-2">Deskripsi</h3>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                        {{ $selectedProject['description'] ?? 'Belum ada deskripsi.' }}
                                    </p>
                                </div>
                                
                                {{-- Subtasks --}}
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                            <flux:icon.list-bullet class="w-4 h-4 text-zinc-500"/>
                                            Subtasks
                                        </h3>
                                        @php
                                            $total = count($selectedProject['subtasks'] ?? []);
                                            $completed = collect($selectedProject['subtasks'] ?? [])->where('is_completed', true)->count();
                                            $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
                                        @endphp
                                        <span class="text-xs font-medium text-zinc-500">{{ $progress }}%</span>
                                    </div>
                                    
                                    <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5 mb-4">
                                        <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-300" style="width: {{ $progress }}%"></div>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        @foreach($selectedProject['subtasks'] ?? [] as $subtask)
                                            <div class="flex flex-col gap-2 p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 {{ $subtask['is_completed'] ? 'bg-zinc-50 dark:bg-zinc-800/30' : 'bg-white dark:bg-zinc-900' }}">
                                                <div class="flex items-start gap-3">
                                                    <div class="pt-0.5">
                                                        <flux:checkbox wire:click="toggleSubtask({{ $subtask['id'] }})" :checked="$subtask['is_completed']" />
                                                    </div>
                                                    <div class="flex-1">
                                                        <span class="text-sm {{ $subtask['is_completed'] ? 'line-through text-zinc-400 dark:text-zinc-500' : 'text-zinc-700 dark:text-zinc-200' }}">
                                                            {{ $subtask['title'] }}
                                                        </span>
                                                        
                                                        @if($subtask['requires_input'])
                                                            <div class="mt-2" x-data="{ inputValue: '{{ $subtask['input_value'] ?? '' }}' }">
                                                                <div class="flex items-center gap-2">
                                                                    <flux:input x-model="inputValue" size="sm" placeholder="Masukkan URL postingan..." class="w-full" />
                                                                    <flux:button size="sm" wire:click="saveSubtaskValue({{ $subtask['id'] }}, inputValue)">Simpan</flux:button>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <flux:button wire:click="deleteSubtask({{ $subtask['id'] }})" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" />
                                                </div>
                                            </div>
                                        @endforeach
                                        
                                        <div class="flex gap-2 mt-4">
                                            <flux:input wire:model="newSubtaskTitle" wire:keydown.enter="addSubtask" placeholder="Tambah subtask baru..." class="w-full" />
                                            <flux:button wire:click="addSubtask" variant="primary">Tambah</flux:button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Sidebar Info --}}
                            <div class="col-span-1 space-y-4">
                                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                    <h4 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-3">Informasi Utama</h4>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            @php
                                                $modalAssignee = !empty($selectedProject['assignees']) ? $selectedProject['assignees'][0]['name'] : 'Unassigned';
                                            @endphp
                                            <span class="text-xs text-zinc-500 block mb-1">Penanggung Jawab</span>
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-[10px] font-bold text-zinc-600 dark:text-zinc-300">
                                                    {{ substr($modalAssignee, 0, 1) }}
                                                </div>
                                                <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $modalAssignee }}</span>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <span class="text-xs text-zinc-500 block mb-1">Status Saat Ini</span>
                                            <span class="inline-flex items-center rounded-md bg-zinc-100 dark:bg-zinc-800 px-2 py-1 text-xs font-medium text-zinc-600 dark:text-zinc-300 ring-1 ring-inset ring-zinc-500/20">
                                                {{ $columns[$selectedProject['status']]['title'] ?? $selectedProject['status'] }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Tab 2: Comments --}}
                    <div x-show="activeTab === 'comments'" class="space-y-6" x-cloak>
                        {{-- Add Comment Form --}}
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 w-8 h-8 mt-1 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-xs">
                                {{ substr(auth()->user()->name, 0, 1) }}
                            </div>
                            <div class="flex-1 space-y-2">
                                <flux:textarea wire:model="newComment" placeholder="Tulis komentar..." rows="2" class="w-full" />
                                <div class="flex justify-end">
                                    <flux:button wire:click="addComment" variant="primary" size="sm">Kirim Komentar</flux:button>
                                </div>
                            </div>
                        </div>

                        {{-- Comments List --}}
                        <div class="space-y-4 mt-6">
                            @forelse($selectedProject['comments'] ?? [] as $comment)
                                <div class="flex gap-3">
                                    <div class="shrink-0 w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center font-bold text-xs text-zinc-600 dark:text-zinc-300">
                                        {{ substr($comment['user']['name'] ?? 'U', 0, 1) }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $comment['user']['name'] ?? 'Unknown' }}</span>
                                                <span class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($comment['created_at'])->diffForHumans() }}</span>
                                            </div>
                                            <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{{ $comment['content'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-10 text-zinc-500">
                                    <flux:icon.chat-bubble-left-ellipsis class="w-10 h-10 mx-auto text-zinc-300 mb-3" />
                                    <p>Belum ada komentar.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Tab 3: Attachments --}}
                    <div x-show="activeTab === 'attachments'" class="space-y-6" x-cloak>
                        {{-- Upload Form --}}
                        <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 flex items-center gap-4">
                            <div class="flex-1">
                                <input type="file" wire:model="attachmentFile" class="block w-full text-sm text-zinc-500 dark:text-zinc-400
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-indigo-50 file:text-indigo-700
                                  dark:file:bg-indigo-900/30 dark:file:text-indigo-400
                                  hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50" />
                                <div wire:loading wire:target="attachmentFile" class="text-xs text-indigo-500 mt-2">Mengunggah...</div>
                            </div>
                            <flux:button wire:click="uploadAttachment" variant="primary" :disabled="!$attachmentFile">Unggah</flux:button>
                        </div>

                        {{-- Attachments Grid --}}
                        @if(!empty($selectedProject['attachments']))
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                @foreach($selectedProject['attachments'] as $attachment)
                                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 bg-white dark:bg-zinc-900 flex flex-col items-center text-center gap-2 relative group">
                                        <div class="w-12 h-12 flex items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-500">
                                            @if(str_contains($attachment['file_type'], 'image'))
                                                <flux:icon.photo class="w-6 h-6" />
                                            @elseif(str_contains($attachment['file_type'], 'pdf'))
                                                <flux:icon.document-text class="w-6 h-6" />
                                            @else
                                                <flux:icon.document class="w-6 h-6" />
                                            @endif
                                        </div>
                                        <div class="w-full">
                                            <p class="text-xs font-medium text-zinc-900 dark:text-zinc-100 truncate w-full px-2" title="{{ $attachment['file_name'] }}">
                                                {{ $attachment['file_name'] }}
                                            </p>
                                            <p class="text-[10px] text-zinc-500">{{ number_format($attachment['file_size'] / 1024, 1) }} KB</p>
                                        </div>
                                        <div class="absolute inset-0 bg-zinc-900/50 rounded-lg opacity-0 group-hover:opacity-100 flex items-center justify-center gap-2 transition-opacity">
                                            <a href="{{ Storage::url($attachment['file_path']) }}" target="_blank" class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-zinc-900 hover:scale-110 transition-transform">
                                                <flux:icon.eye class="w-4 h-4" />
                                            </a>
                                            <a href="{{ Storage::url($attachment['file_path']) }}" download class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-zinc-900 hover:scale-110 transition-transform">
                                                <flux:icon.arrow-down-tray class="w-4 h-4" />
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-10 text-zinc-500">
                                <flux:icon.document class="w-10 h-10 mx-auto text-zinc-300 mb-3" />
                                <p>Belum ada lampiran.</p>
                            </div>
                        @endif
                    </div>

                    {{-- Tab 4: Time Log --}}
                    <div x-show="activeTab === 'time'" class="space-y-6" x-cloak>
                        {{-- Stats --}}
                        @php
                            $totalMinutes = collect($selectedProject['timeLogs'] ?? [])->sum(function($log) {
                                return ($log['hours'] * 60) + $log['minutes'];
                            });
                        @endphp
                        <div class="flex items-center justify-between p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-100 dark:border-indigo-800/30">
                            <div>
                                <p class="text-sm text-indigo-600 dark:text-indigo-400 font-medium">Total Waktu Dihabiskan</p>
                                <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-200">{{ $this->formatTime($totalMinutes) }}</p>
                            </div>
                            <flux:icon.clock class="w-10 h-10 text-indigo-200 dark:text-indigo-800" />
                        </div>

                        {{-- Add Log Form --}}
                        <div class="grid grid-cols-4 gap-3 bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 items-end">
                            <div class="col-span-1">
                                <flux:input type="number" wire:model="timeLogHours" label="Jam" placeholder="0" min="0" />
                            </div>
                            <div class="col-span-1">
                                <flux:input type="number" wire:model="timeLogMinutes" label="Menit" placeholder="0" min="0" max="59" />
                            </div>
                            <div class="col-span-2">
                                <flux:input wire:model="timeLogDescription" label="Deskripsi (opsional)" placeholder="Apa yang Anda kerjakan?" />
                            </div>
                            <div class="col-span-4 mt-2 flex justify-end">
                                <flux:button wire:click="addTimeLog" variant="primary" size="sm">Catat Waktu</flux:button>
                            </div>
                        </div>

                        {{-- Logs List --}}
                        <div class="space-y-3">
                            <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Riwayat Pencatatan</h4>
                            @forelse($selectedProject['timeLogs'] ?? [] as $log)
                                <div class="flex justify-between items-center p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg bg-white dark:bg-zinc-900">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-xs font-bold text-zinc-600 dark:text-zinc-300">
                                            {{ substr($log['user']['name'] ?? 'U', 0, 1) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $log['user']['name'] ?? 'Unknown' }}</p>
                                            <p class="text-xs text-zinc-500">{{ $log['description'] ?: 'Tidak ada deskripsi' }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $this->formatTime(($log['hours'] * 60) + $log['minutes']) }}</p>
                                        <p class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($log['created_at'])->format('d M Y, H:i') }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-6 text-zinc-500">
                                    <p class="text-sm">Belum ada catatan waktu.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Tab 5: Activity --}}
                    <div x-show="activeTab === 'activity'" class="space-y-6" x-cloak>
                        <div class="relative pl-4 space-y-6 border-l-2 border-zinc-200 dark:border-zinc-700 ml-4 mt-4">
                            @forelse($selectedProject['activities'] ?? [] as $activity)
                                <div class="relative">
                                    <div class="absolute -left-[25px] w-3 h-3 rounded-full bg-indigo-500 ring-4 ring-white dark:ring-zinc-900"></div>
                                    <div class="flex flex-col gap-1">
                                        <p class="text-sm text-zinc-800 dark:text-zinc-200">
                                            <span class="font-medium">{{ $activity['user']['name'] ?? 'Sistem' }}</span> 
                                            {{ strtolower(substr($activity['description'], 0, 1)) . substr($activity['description'], 1) }}
                                        </p>
                                        <span class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($activity['created_at'])->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-6 text-zinc-500 border-none -ml-4">
                                    <flux:icon.bolt class="w-8 h-8 mx-auto text-zinc-300 mb-2" />
                                    <p class="text-sm">Belum ada aktivitas terekam.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                </div>
                
                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700 mt-4 shrink-0">
                    <flux:button wire:click="closeModal" variant="ghost">Tutup</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
