<?php

use App\Models\User;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Models\TaskSubtask;
use Modules\Workspace\Models\TaskComment;
use Modules\Workspace\Models\TaskAttachment;
use Modules\Workspace\Models\TaskTimeLog;
use Modules\Workspace\Models\TaskActivity;
use Illuminate\Support\Carbon;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;
use Modules\Workspace\Models\TaskLabel;

new class extends Component {
    use WithFileUploads;

    public $workspace;
    public array $projects = [];
    
    public array $columns = [];

    public $selectedProject = null;
    public string $newSubtaskTitle = '';
    public bool $showProjectModal = false;
    public array $availableLabels = [];

    // State for new items
    public string $newComment = '';
    public $attachmentFile = null;
    public string $timeLogHours = '';
    public string $timeLogMinutes = '';
    public string $timeLogDescription = '';

    public ?string $addingTaskToColumn = null;
    public string $newTaskTitle = '';
    
    public bool $isAddingColumn = false;
    public string $newColumnTitle = '';

    // Member Management State
    public bool $showMembersModal = false;
    public string $searchUserQuery = '';
    public array $searchResults = [];

    public function mount(\Modules\Workspace\Models\Workspace $workspace) {
        $this->workspace = $workspace;
        $this->columns = $workspace->columns()->orderBy('position')->get()->toArray();
        $this->availableLabels = TaskLabel::all()->toArray();
        $this->loadProjects();
        $this->loadMembers();
    }

    public function loadMembers() {
        $this->workspace->load('users');
    }

    #[\Livewire\Attributes\On('task-updated')]
    public function loadProjects() {
        $this->projects = $this->workspace->tasks()->with([
            'subtasks', 'labels', 'assignees', 'comments.user', 'attachments.user', 'activities.user', 'timeLogs.user'
        ])
            ->orderBy('position', 'asc')
            ->get()->toArray();
    }

    public function recordActivity($projectId, $description) {
        TaskActivity::create([
            'task_id' => $projectId,
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
            ->where('workspace_column_id', $this->addingTaskToColumn)
            ->max('position');
            
        $maxPosition = $maxPosition ?? -1;

        $project = Task::create([
            'title' => trim($this->newTaskTitle),
            'workspace_column_id' => $this->addingTaskToColumn,
            'workspace_id' => $this->workspace->id,
            'position' => $maxPosition + 1,
            'platform' => 'General',
        ]);

        $this->recordActivity($project->id, 'Membuat tugas baru');

        $this->addingTaskToColumn = null;
        $this->newTaskTitle = '';
        $this->loadProjects();
    }

    public function addNewColumn() {
        if (empty(trim($this->newColumnTitle))) return;
        
        $maxPosition = $this->workspace->columns()->max('position') ?? -1;
        
        $this->workspace->columns()->create([
            'title' => trim($this->newColumnTitle),
            'color' => 'gray',
            'position' => $maxPosition + 1,
        ]);
        
        $this->newColumnTitle = '';
        $this->isAddingColumn = false;
        
        $this->columns = $this->workspace->columns()->orderBy('position')->get()->toArray();
    }

    // --- Member Management Methods ---
    
    public function updatedSearchUserQuery() {
        if (strlen($this->searchUserQuery) < 2) {
            $this->searchResults = [];
            return;
        }

        $existingUserIds = $this->workspace->users->pluck('id')->toArray();

        $this->searchResults = User::where(function($query) {
                $query->where('name', 'like', '%' . $this->searchUserQuery . '%')
                      ->orWhere('email', 'like', '%' . $this->searchUserQuery . '%');
            })
            ->whereNotIn('id', $existingUserIds)
            ->take(5)
            ->get(['id', 'name', 'email'])
            ->toArray();
    }

    public function addMember($userId) {
        // Ensure user is admin
        $currentUser = $this->workspace->users()->where('user_id', auth()->id())->first();
        if (!$currentUser || $currentUser->pivot->role !== 'admin') {
            return; // Only admins can add members
        }

        $this->workspace->users()->attach($userId, ['role' => 'member']);
        $this->searchUserQuery = '';
        $this->searchResults = [];
        $this->loadMembers();
    }

    public function removeMember($userId) {
        $currentUser = $this->workspace->users()->where('user_id', auth()->id())->first();
        if (!$currentUser || $currentUser->pivot->role !== 'admin') {
            return;
        }

        // Cannot remove the owner or yourself (if you are the only admin)
        if ($this->workspace->owner_id == $userId) return;

        $this->workspace->users()->detach($userId);
        $this->loadMembers();
    }

    public function updateRole($userId, $newRole) {
        $currentUser = $this->workspace->users()->where('user_id', auth()->id())->first();
        if (!$currentUser || $currentUser->pivot->role !== 'admin') {
            return;
        }

        if ($this->workspace->owner_id == $userId) return; // Owner is always admin

        $this->workspace->users()->updateExistingPivot($userId, ['role' => $newRole]);
        $this->loadMembers();
    }
    // --------------------------------

    public function reorder($orderedIds, $newStatus) {
        foreach ($orderedIds as $index => $id) {
            $project = Task::find($id);
            if ($project && $project->workspace_column_id !== (int)$newStatus) {
                $this->recordActivity($id, 'Memindahkan tugas ke kolom ' . ($this->columns[$newStatus]['title'] ?? $newStatus));
            }
            
            if ($project) {
                $project->update([
                    'workspace_column_id' => (int)$newStatus,
                    'position' => $index
                ]);
            }
        }
        $this->loadProjects();
    }

    public function move($projectId, $newStatus) {
        $project = Task::find($projectId);
        if ($project && $project->workspace_column_id !== (int)$newStatus) {
            $this->recordActivity($projectId, 'Memindahkan tugas ke kolom ' . ($this->columns[$newStatus]['title'] ?? $newStatus));
            $project->update(['workspace_column_id' => (int)$newStatus]);
        }
        
        $this->loadProjects();
        
        if ($this->selectedProject && $this->selectedProject['id'] == $projectId) {
            $this->selectedProject = collect($this->projects)->firstWhere('id', $projectId);
        }
    }

};
?>

<div class="h-full w-full flex flex-col space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between px-2 gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('workspaces.index') }}" variant="subtle" size="sm" icon="arrow-left" class="shrink-0" />
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $workspace->name }}</h1>
            </div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1 ml-11">{{ $workspace->description ?? 'Kelola tugas workspace Anda di sini.' }}</p>
        </div>
        <div class="flex items-center gap-4">
            {{-- Members Avatars --}}
            <div class="flex items-center">
                <div class="flex -space-x-2 mr-3">
                    @foreach($workspace->users->take(5) as $member)
                        <div class="w-8 h-8 rounded-full bg-indigo-100 border-2 border-zinc-50 dark:border-zinc-900 flex items-center justify-center text-xs font-bold text-indigo-600 ring-2 ring-transparent hover:ring-indigo-300 hover:z-10 transition-all cursor-pointer" title="{{ $member->name }} ({{ $member->pivot->role }})">
                            {{ substr($member->name, 0, 1) }}
                        </div>
                    @endforeach
                    @if($workspace->users->count() > 5)
                        <div class="w-8 h-8 rounded-full bg-zinc-100 border-2 border-zinc-50 dark:border-zinc-900 flex items-center justify-center text-xs font-bold text-zinc-600">
                            +{{ $workspace->users->count() - 5 }}
                        </div>
                    @endif
                </div>
                <flux:button wire:click="$set('showMembersModal', true)" variant="subtle" size="sm" icon="users">Members</flux:button>
            </div>

            <div class="w-px h-6 bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:button variant="primary" icon="plus" wire:click="showAddTask({{ $columns[0]['id'] ?? 0 }})">New Task</flux:button>
        </div>
    </div>
    {{-- Kanban Board Area --}}
    <div class="flex-1 overflow-x-auto pb-4">
        <div class="flex gap-4 min-w-max h-full items-start px-2">
            @foreach($columns as $columnData)
                @php $statusKey = $columnData['id']; @endphp
                @php
                    $columnProjects = collect($projects)->where('workspace_column_id', $statusKey);
                    
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
                            <div @click="$flux.modal('task-detail').show(); window.dispatchEvent(new CustomEvent('task-loading')); $wire.$dispatch('open-task-modal', { taskId: {{ $project['id'] }}, columns: {{ json_encode($columns) }} })"
                                 data-id="{{ $project['id'] }}"
                                 class="bg-white dark:bg-zinc-900 rounded-lg p-3 shadow-sm border border-zinc-200 dark:border-zinc-800 group relative cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/80 hover:border-indigo-300 dark:hover:border-indigo-700 transition-all duration-200 origin-center">
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
                        
                        <!-- Add Task Inline Form -->
                        @if($addingTaskToColumn == $statusKey)
                            <div class="bg-white dark:bg-zinc-800 p-3 rounded-lg border border-indigo-200 dark:border-indigo-800/30 shadow-sm space-y-2">
                                <flux:input wire:model="newTaskTitle" placeholder="Judul task baru..." wire:keydown.enter="addTask" />
                                <div class="flex items-center gap-2 justify-end mt-2">
                                    <flux:button wire:click="cancelAddTask" size="xs" variant="ghost">Batal</flux:button>
                                    <flux:button wire:click="addTask" size="xs" variant="primary">Tambah</flux:button>
                                </div>
                            </div>
                        @else
                            <flux:button wire:click="showAddTask('{{ $statusKey }}')" variant="subtle" class="w-full justify-center !border-dashed">
                                <flux:icon.plus class="w-4 h-4 mr-2"/>
                                Add task
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
            
            {{-- Add Column Button/Form --}}
            <div class="w-[300px] flex-shrink-0 flex flex-col bg-zinc-50/50 dark:bg-zinc-800/30 rounded-xl h-fit border border-dashed border-zinc-300 dark:border-zinc-700">
                @if($isAddingColumn)
                    <div class="p-3 space-y-2">
                        <flux:input wire:model="newColumnTitle" placeholder="Nama kolom baru..." wire:keydown.enter="addNewColumn" autofocus />
                        <div class="flex items-center gap-2 justify-end mt-2">
                            <flux:button wire:click="$set('isAddingColumn', false)" size="xs" variant="ghost">Batal</flux:button>
                            <flux:button wire:click="addNewColumn" size="xs" variant="primary">Tambah</flux:button>
                        </div>
                    </div>
                @else
                    <div class="p-2">
                        <flux:button wire:click="$set('isAddingColumn', true)" variant="ghost" class="w-full justify-center !text-zinc-500">
                            <flux:icon.plus class="w-4 h-4 mr-2"/>
                            Tambah Kolom
                        </flux:button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Task Detail Modal Component --}}
    <livewire:workspace.task-detail-modal :workspace="$workspace" />

    {{-- Members Management Modal --}}
    <flux:modal wire:model="showMembersModal" class="w-full max-w-2xl">
        <div class="p-4 space-y-6">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-700 pb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Anggota Workspace</h2>
            </div>

            {{-- Invite Form --}}
            @php
                $currentUserRole = $workspace->users->where('id', auth()->id())->first()?->pivot->role ?? 'member';
                $isAdmin = $currentUserRole === 'admin';
            @endphp

            @if($isAdmin)
                <div class="bg-indigo-50/50 dark:bg-indigo-900/10 p-4 rounded-xl border border-indigo-100 dark:border-indigo-800/30">
                    <h3 class="text-sm font-medium text-indigo-900 dark:text-indigo-200 mb-3">Undang Anggota Baru</h3>
                    <div class="relative" x-data="{ query: @entangle('searchUserQuery') }">
                        <flux:input wire:model.live.debounce.300ms="searchUserQuery" placeholder="Cari berdasarkan nama atau email..." icon="magnifying-glass" />
                        
                        @if(strlen($searchUserQuery) >= 2)
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 max-h-60 overflow-y-auto">
                                @forelse($searchResults as $user)
                                    <div class="flex items-center justify-between p-3 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 cursor-pointer border-b border-zinc-100 dark:border-zinc-700/50 last:border-0" wire:click="addMember({{ $user['id'] }})">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center font-bold text-xs text-zinc-600 dark:text-zinc-300">
                                                {{ substr($user['name'], 0, 1) }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $user['name'] }}</p>
                                                <p class="text-xs text-zinc-500">{{ $user['email'] }}</p>
                                            </div>
                                        </div>
                                        <flux:button size="sm" variant="ghost" class="text-indigo-600 hover:text-indigo-700">Tambah</flux:button>
                                    </div>
                                @empty
                                    <div class="p-4 text-center text-sm text-zinc-500">
                                        Tidak ada pengguna ditemukan.
                                    </div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Members List --}}
            <div class="space-y-3">
                <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Anggota Saat Ini ({{ $workspace->users->count() }})</h3>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
                    @foreach($workspace->users as $member)
                        <div class="p-3 flex items-center justify-between bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center font-bold text-sm text-indigo-600 dark:text-indigo-400">
                                    {{ substr($member->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $member->name }}</p>
                                        @if($workspace->owner_id == $member->id)
                                            <span class="inline-flex items-center rounded-md bg-yellow-50 dark:bg-yellow-900/20 px-1.5 py-0.5 text-[10px] font-medium text-yellow-800 dark:text-yellow-500 ring-1 ring-inset ring-yellow-600/20">Owner</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-zinc-500">{{ $member->email }}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                @if($isAdmin && $workspace->owner_id != $member->id)
                                    <flux:select wire:change="updateRole({{ $member->id }}, $event.target.value)" size="sm" class="w-32">
                                        <option value="admin" {{ $member->pivot->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                        <option value="member" {{ $member->pivot->role === 'member' ? 'selected' : '' }}>Member</option>
                                    </flux:select>
                                    
                                    <flux:button wire:click="removeMember({{ $member->id }})" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" />
                                @else
                                    <span class="text-sm text-zinc-500 capitalize px-3 py-1 bg-zinc-100 dark:bg-zinc-800 rounded-md">
                                        {{ $member->pivot->role }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </flux:modal>
</div>
