<?php

use App\Models\User;
use App\Events\WorkspaceTaskUpdated;
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
use Livewire\Attributes\Lazy;

new #[Lazy] class extends Component {
    use WithFileUploads;

    public $workspace;
    public array $projects = [];
    
    public array $columns = [];

    public $selectedProject = null;
    public string $newSubtaskTitle = '';
    public bool $showProjectModal = false;
    public bool $showOkrModal = false;
    public bool $showKpiModal = false;
    public bool $showInfoModal = false;
    public bool $showRichEditorModal = false;
    public string $tempWorkspaceDescription = '';
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
    public string $newColumnColor = 'gray';
    
    public ?string $editingColumnId = null;
    public string $editingColumnTitle = '';
    public string $editingColumnColor = 'gray';
    public string $editingColumnType = 'normal';
    public bool $editingColumnIsHidden = false;
    public bool $showEditColumnModal = false;
    
    public ?string $columnToDeleteId = null;
    public ?string $fallbackColumnId = null;
    public int $tasksCountToMove = 0;
    
    // Sub-column add form state
    public ?string $addingSubColumnToParent = null;
    public string $newSubColumnTitle = '';

    // Member Management State
    public bool $showMembersModal = false;
    public string $searchUserQuery = '';
    public array $searchResults = [];
    public bool $isAdminOrLeader = false;
    public bool $isObserver = false;

    public function mount(\Modules\Workspace\Models\Workspace $workspace) {
        $this->workspace = $workspace;
        $this->columns = $workspace->columns()->orderBy('position')->get()->toArray();
        $this->availableLabels = TaskLabel::all()->toArray();

        // Compute role once — used in view and loadProjects
        $user = auth()->user();
        $workspaceRole = $workspace->users()->where('user_id', $user->id)->first()?->pivot->role ?? null;
        
        $isOwner = $workspace->owner_id === $user->id;
        $isSuperAdmin = $user->hasRole(['Manager', 'Super Admin']);
        
        $this->isAdminOrLeader = $isOwner || in_array($workspaceRole, ['admin', 'leader']) || $isSuperAdmin;
        $this->isObserver = $workspaceRole === 'pengawas';

        // Block access if user has no role in this workspace (not member, not owner, not superadmin)
        if (!$this->isAdminOrLeader && !$this->isObserver && $workspaceRole !== 'member') {
            abort(403, 'Anda tidak di-assign atau tidak memiliki akses ke Workspace ini.');
        }

        $this->loadProjects();
        $this->loadMembers();
    }

    public function loadMembers() {
        $this->workspace->load('users');
        $this->updatedSearchUserQuery();
    }

    public function placeholder()
    {
        return '
        <div class="h-screen w-full flex flex-col bg-zinc-50 dark:bg-zinc-950 overflow-hidden">
            <!-- Header Skeleton -->
            <div class="h-[72px] border-b border-zinc-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md flex items-center justify-between px-6 shrink-0 relative z-20">
                <div class="flex items-center gap-4">
                    <div class="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-800 animate-pulse"></div>
                    <div class="w-64 h-6 rounded bg-zinc-200 dark:bg-zinc-800 animate-pulse"></div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="w-32 h-8 rounded-lg bg-zinc-200 dark:bg-zinc-800 animate-pulse"></div>
                    <div class="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-800 animate-pulse"></div>
                </div>
            </div>
            
            <!-- Board Body Skeleton -->
            <div class="flex-1 overflow-x-auto overflow-y-hidden p-6 relative z-10">
                <div class="flex h-full gap-6 h-full items-start">
                    <!-- Column 1 -->
                    <div class="w-80 shrink-0 flex flex-col rounded-2xl bg-zinc-100/50 dark:bg-zinc-900/50 p-4 border border-zinc-200/50 dark:border-zinc-800/50">
                        <div class="w-32 h-5 mb-5 rounded bg-zinc-200 dark:bg-zinc-800 animate-pulse"></div>
                        <div class="w-full h-24 mb-3 rounded-xl bg-white dark:bg-zinc-800 shadow-sm animate-pulse"></div>
                        <div class="w-full h-32 mb-3 rounded-xl bg-white dark:bg-zinc-800 shadow-sm animate-pulse"></div>
                        <div class="w-full h-20 mb-3 rounded-xl bg-white dark:bg-zinc-800 shadow-sm animate-pulse"></div>
                    </div>
                    <!-- Column 2 -->
                    <div class="w-80 shrink-0 flex flex-col rounded-2xl bg-zinc-100/50 dark:bg-zinc-900/50 p-4 border border-zinc-200/50 dark:border-zinc-800/50">
                        <div class="w-24 h-5 mb-5 rounded bg-zinc-200 dark:bg-zinc-800 animate-pulse"></div>
                        <div class="w-full h-32 mb-3 rounded-xl bg-white dark:bg-zinc-800 shadow-sm animate-pulse"></div>
                        <div class="w-full h-20 mb-3 rounded-xl bg-white dark:bg-zinc-800 shadow-sm animate-pulse"></div>
                    </div>
                    <!-- Column 3 -->
                    <div class="w-80 shrink-0 flex flex-col rounded-2xl bg-zinc-100/50 dark:bg-zinc-900/50 p-4 border border-zinc-200/50 dark:border-zinc-800/50">
                        <div class="w-40 h-5 mb-5 rounded bg-zinc-200 dark:bg-zinc-800 animate-pulse"></div>
                        <div class="w-full h-24 mb-3 rounded-xl bg-white dark:bg-zinc-800 shadow-sm animate-pulse"></div>
                        <div class="w-full h-24 mb-3 rounded-xl bg-white dark:bg-zinc-800 shadow-sm animate-pulse"></div>
                        <div class="w-full h-24 mb-3 rounded-xl bg-white dark:bg-zinc-800 shadow-sm animate-pulse"></div>
                    </div>
                    <!-- Column 4 -->
                    <div class="w-80 shrink-0 flex flex-col rounded-2xl bg-zinc-100/50 dark:bg-zinc-900/50 p-4 border border-zinc-200/50 dark:border-zinc-800/50">
                        <div class="w-32 h-5 mb-5 rounded bg-zinc-200 dark:bg-zinc-800 animate-pulse"></div>
                        <div class="w-full h-40 mb-3 rounded-xl bg-white dark:bg-zinc-800 shadow-sm animate-pulse"></div>
                    </div>
                </div>
            </div>
        </div>
        ';
    }

    public function openWorkspaceEditor() {
        if (!$this->isAdminOrLeader) return;
        $this->tempWorkspaceDescription = $this->workspace->description ?? '';
        $this->showInfoModal = false;
        $this->showRichEditorModal = true;
    }

    public function saveWorkspaceDescription() {
        if (!$this->isAdminOrLeader) return;
        $this->workspace->update(['description' => $this->tempWorkspaceDescription]);
        $this->showRichEditorModal = false;
        $this->showInfoModal = true;
    }

    #[\Livewire\Attributes\On('task-updated')]
    public function loadProjects() {
        $query = $this->workspace->tasks()->with([
            'subtasks:id,task_id,is_completed', 
            'assignees:id,name,avatar',
            'labels:id,name,color',
            'userReads' => function($q) {
                $q->where('user_id', auth()->id())->select('last_read_at');
            }
        ])->withCount(['comments', 'attachments', 'urls'])->orderBy('position', 'asc');

        // Non-admin/manager members only see tasks assigned to themselves
        // Observers see all tasks but cannot edit them (handled in UI)
        if (!$this->isAdminOrLeader && !$this->isObserver) {
            $query->whereHas('assignees', fn($q) => $q->where('user_id', auth()->id()));
        }

        $this->projects = $query->get()->toArray();
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
        WorkspaceTaskUpdated::safeDispatch($this->workspace->id, 'task_added', [
            'user' => auth()->user()->name,
            'user_avatar' => auth()->user()->avatar ? \Illuminate\Support\Facades\Storage::url(auth()->user()->avatar) : null,
            'message' => 'Membuat tugas baru "' . trim($this->newTaskTitle) . '"'
        ]);
    }

    public function addNewColumn() {
        if (empty(trim($this->newColumnTitle))) return;
        
        $maxPosition = $this->workspace->columns()->max('position') ?? -1;
        
        $this->workspace->columns()->create([
            'title' => trim($this->newColumnTitle),
            'color' => $this->newColumnColor ?: 'gray',
            'position' => $maxPosition + 1,
        ]);
        
        $this->newColumnTitle = '';
        $this->newColumnColor = 'gray';
        $this->isAddingColumn = false;
        
        $this->columns = $this->workspace->columns()->orderBy('position')->get()->toArray();
        $this->dispatch('kanban-reinit');
        WorkspaceTaskUpdated::safeDispatch($this->workspace->id, 'column_added', [
            'user' => auth()->user()->name,
            'user_avatar' => auth()->user()->avatar ? \Illuminate\Support\Facades\Storage::url(auth()->user()->avatar) : null,
            'message' => 'Menambahkan kolom baru'
        ]);
    }
    
    public function showAddSubColumnForm($parentId) {
        if (!$this->isAdminOrLeader) return;
        $this->addingSubColumnToParent = $parentId;
        $this->newSubColumnTitle = '';
    }
    
    public function cancelAddSubColumn() {
        $this->addingSubColumnToParent = null;
        $this->newSubColumnTitle = '';
    }
    
    public function addSubColumn() {
        if (!$this->isAdminOrLeader || !$this->addingSubColumnToParent) return;
        
        $title = trim($this->newSubColumnTitle);
        if (empty($title)) return;
        
        $maxPosition = $this->workspace->columns()->where('parent_id', $this->addingSubColumnToParent)->max('position') ?? -1;
        
        $this->workspace->columns()->create([
            'title' => $title,
            'color' => 'gray',
            'position' => $maxPosition + 1,
            'parent_id' => $this->addingSubColumnToParent
        ]);
        
        $this->addingSubColumnToParent = null;
        $this->newSubColumnTitle = '';
        $this->columns = $this->workspace->columns()->orderBy('position')->get()->toArray();
        $this->dispatch('kanban-reinit');
    }
    
    public function makeColumnGroup($columnId) {
        // Takes a standalone column and makes it a parent group with itself as first child
        if (!$this->isAdminOrLeader) return;
        
        // The column stays with parent_id null - it IS the parent group
        // Just add a new child sub-column
        $this->workspace->columns()->create([
            'title' => 'Sub Kolom',
            'color' => 'gray',
            'position' => 0,
            'parent_id' => $columnId
        ]);
        
        $this->columns = $this->workspace->columns()->orderBy('position')->get()->toArray();
        $this->dispatch('kanban-reinit');
    }
    
    public function editColumn($columnId) {
        if (!$this->isAdminOrLeader) return;
        
        $col = collect($this->columns)->firstWhere('id', (int)$columnId);
        if (!$col) {
            $col = collect($this->columns)->firstWhere('id', $columnId);
        }
        if ($col) {
            $this->editingColumnId = $columnId;
            $this->editingColumnTitle = $col['title'];
            $this->editingColumnColor = $col['color'] ?? 'gray';
            $this->editingColumnType = $col['type'] ?? 'normal';
            $this->editingColumnIsHidden = (bool)($col['is_hidden'] ?? false);
            $this->dispatch('open-edit-column-modal');
        }
    }
    
    public function updateColumn() {
        if (!$this->isAdminOrLeader || !$this->editingColumnId) return;
        
        $this->workspace->columns()->where('id', $this->editingColumnId)->update([
            'title' => trim($this->editingColumnTitle),
            'color' => $this->editingColumnColor ?: 'gray',
            'type' => $this->editingColumnType,
            'is_hidden' => $this->editingColumnIsHidden
        ]);
        
        $this->columns = $this->workspace->columns()->orderBy('position')->get()->toArray();
        $this->dispatch('close-edit-column-modal');
    }
    
    public function deleteColumn($columnId) {
        if (!$this->isAdminOrLeader) return;
        
        $subCols = collect($this->columns)->where('parent_id', $columnId)->pluck('id')->toArray();
        $subSubCols = collect($this->columns)->whereIn('parent_id', $subCols)->pluck('id')->toArray();
        $deletedColumnIds = array_merge([$columnId], $subCols, $subSubCols);
        
        $count = \Modules\Workspace\Models\Task::whereIn('workspace_column_id', $deletedColumnIds)->count();
        
        if ($count > 0) {
            $this->columnToDeleteId = $columnId;
            $this->tasksCountToMove = $count;
            // Set default fallback to first available leaf column
            $available = collect($this->columns)->whereNotIn('id', $deletedColumnIds);
            foreach ($available->sortBy('position') as $col) {
                if ($available->where('parent_id', $col['id'])->count() == 0) {
                    $this->fallbackColumnId = $col['id'];
                    break;
                }
            }
            $this->dispatch('open-delete-column-modal');
        } else {
            // Delete directly
            $this->workspace->columns()->whereIn('id', $deletedColumnIds)->delete();
            $this->columns = $this->workspace->columns()->orderBy('position')->get()->toArray();
            $this->dispatch('kanban-reinit');
        }
    }
    
    public function confirmDeleteColumn() {
        if (!$this->isAdminOrLeader || !$this->columnToDeleteId) return;
        
        $subCols = collect($this->columns)->where('parent_id', $this->columnToDeleteId)->pluck('id')->toArray();
        $subSubCols = collect($this->columns)->whereIn('parent_id', $subCols)->pluck('id')->toArray();
        $deletedColumnIds = array_merge([$this->columnToDeleteId], $subCols, $subSubCols);
        
        if ($this->fallbackColumnId && !in_array($this->fallbackColumnId, $deletedColumnIds)) {
            // Move tasks to fallback column
            $maxPosition = \Modules\Workspace\Models\Task::where('workspace_column_id', $this->fallbackColumnId)->max('position') ?? -1;
            $tasks = \Modules\Workspace\Models\Task::whereIn('workspace_column_id', $deletedColumnIds)->orderBy('position')->get();
            
            foreach ($tasks as $task) {
                $maxPosition++;
                $task->update([
                    'workspace_column_id' => $this->fallbackColumnId,
                    'position' => $maxPosition
                ]);
            }
        } else {
            // If somehow fallback is invalid, just delete tasks to avoid orphans
            \Modules\Workspace\Models\Task::whereIn('workspace_column_id', $deletedColumnIds)->delete();
        }
        
        $this->workspace->columns()->whereIn('id', $deletedColumnIds)->delete();
        $this->columns = $this->workspace->columns()->orderBy('position')->get()->toArray();
        $this->dispatch('close-delete-column-modal');
        $this->columnToDeleteId = null;
        $this->fallbackColumnId = null;
        $this->dispatch('kanban-reinit');
    }
    
    public function moveColumnAndReorder($columnId, $newParentId, $orderedIds) {
        if (!$this->isAdminOrLeader) return;
        
        $newParentId = empty($newParentId) ? null : $newParentId;
        if ((string)$newParentId === (string)$columnId) return; // Prevent setting itself as parent
        
        $this->workspace->columns()->where('id', $columnId)->update(['parent_id' => $newParentId]);
        
        foreach ($orderedIds as $index => $id) {
            $this->workspace->columns()->where('id', $id)->update(['position' => $index]);
        }
        
        $this->columns = $this->workspace->columns()->orderBy('position')->get()->toArray();
        $this->dispatch('kanban-reinit');
    }

    // --- Member Management Methods ---
    
    public function updatedSearchUserQuery() {
        $existingUserIds = $this->workspace->users->pluck('id')->toArray();

        $query = User::whereNotIn('id', $existingUserIds);

        if (trim($this->searchUserQuery) !== '') {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchUserQuery . '%')
                  ->orWhere('email', 'like', '%' . $this->searchUserQuery . '%');
            });
        }

        $this->searchResults = $query->take(10)->get(['id', 'name', 'email'])->toArray();
    }

    public function addMember($userId) {
        if (!$this->isAdminOrLeader) return;

        $this->workspace->users()->attach($userId, ['role' => 'member']);
        $this->searchUserQuery = '';
        $this->searchResults = [];
        $this->loadMembers();
    }

    public function removeMember($userId) {
        if (!$this->isAdminOrLeader) return;

        // Cannot remove the owner
        if ($this->workspace->owner_id == $userId) return;

        $this->workspace->users()->detach($userId);
        $this->loadMembers();
    }

    public function updateRole($userId, $newRole) {
        if (!$this->isAdminOrLeader) return;

        if ($this->workspace->owner_id == $userId) return; // Owner is always admin

        $this->workspace->users()->updateExistingPivot($userId, ['role' => $newRole]);
        $this->loadMembers();
    }
    // --------------------------------

    private function handleKeyResultProgressUpdate($project, $oldStatus, $newStatus) {
        if ($oldStatus === (int)$newStatus) return;
        
        $oldColumn = collect($this->columns)->firstWhere('id', $oldStatus);
        $newColumn = collect($this->columns)->firstWhere('id', (int)$newStatus);
        
        if (!$oldColumn || !$newColumn) return;
        
        $doneTitles = ['done', 'selesai', 'completed', 'finish', 'finished'];
        
        $isMovingToDone = in_array(strtolower($newColumn['title']), $doneTitles);
        $isMovingFromDone = in_array(strtolower($oldColumn['title']), $doneTitles);
        
        if ($isMovingToDone && !$isMovingFromDone) {
            $project->load('keyResults');
            foreach ($project->keyResults as $kr) {
                $kr->increment('current_value', $kr->pivot->contribution ?? 1);
            }
        } elseif ($isMovingFromDone && !$isMovingToDone) {
            $project->load('keyResults');
            foreach ($project->keyResults as $kr) {
                // Prevent negative values just in case
                $kr->update(['current_value' => max(0, $kr->current_value - ($kr->pivot->contribution ?? 1))]);
            }
        }
    }

    private function canMoveTask($project, $newStatusId) {
        if ($this->isAdminOrLeader) return true;
        
        $oldColumn = collect($this->columns)->firstWhere('id', $project->workspace_column_id);
        $newColumn = collect($this->columns)->firstWhere('id', (int)$newStatusId);
        
        $oldType = $oldColumn['type'] ?? 'normal';
        $newType = $newColumn['type'] ?? 'normal';
        
        // Members cannot move out of Backlog, Review, Done
        if (in_array($oldType, ['backlog', 'review', 'done']) && $project->workspace_column_id !== (int)$newStatusId) {
            return false;
        }
        
        // Members cannot move INTO Done
        if ($newType === 'done' && $project->workspace_column_id !== (int)$newStatusId) {
            return false;
        }
        
        return true;
    }

    public function reorder($orderedIds, $newStatus) {
        $columnTitle = collect($this->columns)->firstWhere('id', (int)$newStatus)['title'] ?? $newStatus;
        foreach ($orderedIds as $index => $id) {
            $project = Task::find($id);
            if ($project) {
                if (!$this->canMoveTask($project, $newStatus)) {
                    $this->dispatch('toast', ['message' => 'Anda tidak memiliki izin memindahkan task ini.', 'type' => 'error']);
                    continue;
                }
                if ($project->workspace_column_id !== (int)$newStatus) {
                    $this->recordActivity($id, 'Memindahkan tugas ke kolom ' . $columnTitle);
                    $this->handleKeyResultProgressUpdate($project, $project->workspace_column_id, $newStatus);
                }
            }
            
            if ($project) {
                // If it changed columns, mark as significant update
                $isNewColumn = $project->workspace_column_id !== (int)$newStatus;
                
                $updateData = [
                    'workspace_column_id' => (int)$newStatus,
                    'position' => $index,
                ];
                
                if ($isNewColumn) {
                    $updateData['last_significant_update_at'] = now();
                }
                
                $project->update($updateData);
                
                if ($isNewColumn) {
                    $project->userReads()->syncWithoutDetaching([auth()->id() => ['last_read_at' => now()]]);
                }
            }
        }
        $this->loadProjects();
        WorkspaceTaskUpdated::safeDispatch($this->workspace->id, 'task_reordered', [
            'task_id' => $id ?? null,
            'user' => auth()->user()->name,
            'user_avatar' => auth()->user()->avatar ? \Illuminate\Support\Facades\Storage::url(auth()->user()->avatar) : null,
            'message' => 'Mengurutkan ulang kartu tugas'
        ]);
    }

    public function move($projectId, $newStatus) {
        $columnTitle = collect($this->columns)->firstWhere('id', (int)$newStatus)['title'] ?? $newStatus;
        $project = Task::find($projectId);
        if ($project && $project->workspace_column_id !== (int)$newStatus) {
            if (!$this->canMoveTask($project, $newStatus)) {
                $this->dispatch('toast', ['message' => 'Anda tidak memiliki izin memindahkan task ini.', 'type' => 'error']);
                $this->loadProjects();
                $this->dispatch('kanban-reinit');
                return;
            }
            $this->recordActivity($projectId, 'Memindahkan tugas ke kolom ' . $columnTitle);
            $this->handleKeyResultProgressUpdate($project, $project->workspace_column_id, $newStatus);
            $project->update([
                'workspace_column_id' => (int)$newStatus,
                'last_significant_update_at' => now()
            ]);
            $project->userReads()->syncWithoutDetaching([auth()->id() => ['last_read_at' => now()]]);
        }
        
        $this->loadProjects();
        WorkspaceTaskUpdated::safeDispatch($this->workspace->id, 'task_moved', [
            'task_id' => $projectId,
            'user' => auth()->user()->name,
            'user_avatar' => auth()->user()->avatar ? \Illuminate\Support\Facades\Storage::url(auth()->user()->avatar) : null,
            'message' => 'Memindahkan tugas ke kolom lain'
        ]);
        
        if ($this->selectedProject && $this->selectedProject['id'] == $projectId) {
            $this->selectedProject = collect($this->projects)->firstWhere('id', $projectId);
        }
    }

};
?>

<div
    class="h-full flex flex-col flex-1 relative bg-slate-50 dark:bg-zinc-900 overflow-hidden"
    x-data="{ bgOpacity: localStorage.getItem('board_bgOpacity') !== null ? Number(localStorage.getItem('board_bgOpacity')) : 15, showWorkspaceEditor: false }"
    @bg-opacity-changed.window="
        bgOpacity = Number($event.detail); 
        localStorage.setItem('board_bgOpacity', bgOpacity);
        if (document.getElementById('board-background-layer')) {
            document.getElementById('board-background-layer').style.opacity = bgOpacity / 100;
        }
    "
    x-on:open-edit-column-modal.window="$nextTick(() => $flux.modal('edit-column').show())"
>
    <style>
        @keyframes corner-wave {
            0% { transform: scale(0); opacity: 0.8; }
            75% { transform: scale(25); opacity: 0; }
            100% { transform: scale(25); opacity: 0; }
        }
        .animate-card-wave::after {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(14,165,233,0.5) 0%, rgba(14,165,233,0.15) 50%, transparent 90%);
            animation: corner-wave 4.5s infinite cubic-bezier(0.2, 0.8, 0.2, 1);
            pointer-events: none;
        }
        .dark .animate-card-wave::after {
            background: radial-gradient(circle, rgba(14,165,233,0.6) 0%, rgba(14,165,233,0.2) 50%, transparent 90%);
        }
        @keyframes card-shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-3px) rotate(-1deg); }
            20%, 40%, 60%, 80% { transform: translateX(3px) rotate(1deg); }
        }
        .animate-card-shake {
            animation: card-shake 0.7s cubic-bezier(0.36, 0.07, 0.19, 0.97) 1;
        }
    </style>

    {{-- Background Layer --}}
    <div id="board-background-layer" class="absolute inset-0 z-0 pointer-events-none transition-opacity duration-300" :style="`opacity: ${bgOpacity / 100}`">
        @if($workspace->cover_image)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($workspace->cover_image) }}" class="w-full h-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-b from-transparent to-white/70 dark:to-zinc-900/70"></div>
        @else
            <div class="absolute inset-0 opacity-[0.2]" style="background-image: radial-gradient(#000 1px, transparent 1px); background-size: 24px 24px;"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-100 via-white to-blue-100 dark:from-indigo-950/70 dark:via-zinc-900 dark:to-blue-950/70"></div>
        @endif
    </div>

    {{-- Main Content --}}
    <div class="relative z-10 flex flex-col flex-1 h-full">
        <style>
            .kanban-col-container:not(:has(> [data-id])) .empty-state {
                display: flex !important;
            }
        </style>
<x-kanban.board
    component-id="workspace-{{ $workspace->id }}"
    :workspace-id="$workspace->id"
    :title="$workspace->name"
    :subtitle="!empty($workspace->description) ? \Illuminate\Support\Str::words(html_entity_decode(strip_tags($workspace->description)), 5) : 'Kelola tugas workspace Anda di sini.'"
    search-placeholder="Cari task...">
    
    <x-slot:subtitleSuffix>
        <button wire:click="$set('showInfoModal', true)" class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 flex items-center gap-0.5 outline-none font-semibold cursor-pointer">
            <flux:icon.information-circle class="w-3 h-3" />
            <span class="hover:underline underline-offset-2">Panduan</span>
        </button>
    </x-slot:subtitleSuffix>


    {{-- Header Actions: back button, avatars, Members/OKR/KPI --}}
    <x-slot:actions>
        <flux:button href="{{ route('workspaces.index') }}" variant="subtle" size="sm" icon="arrow-left" class="shrink-0" />

        <div class="w-px h-5 bg-zinc-200 dark:bg-zinc-700 mx-0.5 hidden sm:block"></div>

        <div class="sm:hidden">
            <flux:button wire:click="$set('showInfoModal', true)" variant="subtle" size="sm" icon="information-circle" class="!text-indigo-500 hover:!bg-indigo-50 dark:hover:!bg-indigo-500/20" />
        </div>

        {{-- Avatars --}}
        <div class="flex -space-x-2 shrink-0">
            @foreach($workspace->users->take(3) as $i => $member)
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border-2 border-white dark:border-zinc-900 overflow-hidden {{ $i >= 1 ? 'hidden sm:block' : '' }}"
                     title="{{ $member->name }} ({{ $member->pivot->role }})">
                    @if($member->avatar)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($member->avatar) }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-xs font-bold text-indigo-600 dark:text-indigo-300">
                            {{ substr($member->name, 0, 1) }}
                        </div>
                    @endif
                </div>
            @endforeach
            @if($workspace->users->count() > 1)
                <div class="w-7 h-7 sm:hidden rounded-full bg-indigo-50 dark:bg-indigo-900/30 border-2 border-white dark:border-zinc-900 flex items-center justify-center text-[10px] font-bold text-indigo-600 dark:text-indigo-400">
                    +{{ $workspace->users->count() - 1 }}
                </div>
            @endif
            @if($workspace->users->count() > 3)
                <div class="hidden sm:flex w-8 h-8 rounded-full bg-zinc-100 dark:bg-zinc-800 border-2 border-white dark:border-zinc-900 items-center justify-center text-xs font-bold text-zinc-600 dark:text-zinc-400">
                    +{{ $workspace->users->count() - 3 }}
                </div>
            @endif
        </div>

        <div class="w-px h-5 bg-zinc-200 dark:bg-zinc-700 mx-0.5"></div>

        {{-- Mobile: icon only; Desktop: icon + text --}}
        <div class="sm:hidden flex items-center gap-0.5">
            <flux:button wire:click="$set('showMembersModal', true)" variant="subtle" size="sm" icon="users" title="Members" />
            <flux:button variant="subtle" size="sm" icon="flag" wire:click="$set('showOkrModal', true)" title="OKR" />
            <flux:button variant="subtle" size="sm" icon="chart-bar" wire:click="$set('showKpiModal', true)" title="KPI" />
            
            <div class="relative" x-data="{ showBgSettings: false }" @click.outside="showBgSettings = false">
                <button type="button" @click="showBgSettings = !showBgSettings" class="p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md transition-colors" title="Background Settings">
                    <flux:icon.photo class="w-5 h-5" />
                </button>
                <div x-show="showBgSettings" x-transition.opacity style="display: none;" class="absolute right-0 top-full mt-2 w-48 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl p-4 z-[9999]">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Opacity</span>
                        <span class="text-xs font-bold text-zinc-500" x-text="bgOpacity + '%'"></span>
                    </div>
                    <input type="range" :value="bgOpacity" @input="$dispatch('bg-opacity-changed', $event.target.value)" min="0" max="100" class="w-full accent-indigo-600 cursor-pointer">
                </div>
            </div>
        </div>
        <div class="hidden sm:flex items-center gap-1">
            <flux:button wire:click="$set('showMembersModal', true)" variant="subtle" size="sm" icon="users">Members</flux:button>
            <flux:button variant="subtle" size="sm" icon="flag" wire:click="$set('showOkrModal', true)">OKR</flux:button>
            <flux:button variant="subtle" size="sm" icon="chart-bar" wire:click="$set('showKpiModal', true)">KPI</flux:button>

            <div class="relative" x-data="{ showBgSettings: false }" @click.outside="showBgSettings = false">
                <button type="button" @click="showBgSettings = !showBgSettings" class="p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md transition-colors" title="Background Settings">
                    <flux:icon.photo class="w-5 h-5" />
                </button>
                <div x-show="showBgSettings" x-transition.opacity style="display: none;" class="absolute right-0 top-full mt-2 w-56 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl p-4 z-[9999]">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Background Opacity</span>
                        <span class="text-xs font-bold text-zinc-500" x-text="bgOpacity + '%'"></span>
                    </div>
                    <input type="range" :value="bgOpacity" @input="$dispatch('bg-opacity-changed', $event.target.value)" min="0" max="100" class="w-full accent-indigo-600 cursor-pointer">
                </div>
            </div>
        </div>
    </x-slot:actions>

    <x-slot:kanban_layout>
        {{-- 
            TOP-LEVEL SORTABLE: handles standalone columns AND group wrappers
            handle: .column-header-handle — present on group headers AND x-kanban.column headers
            Items: direct children with data-column-id
        --}}
        <div class="flex gap-3 sm:gap-4 lg:gap-6 items-stretch min-w-full w-max h-full px-2 lg:px-0 before:content-[''] before:m-auto after:content-[''] after:m-auto"
             id="kanban-top-level"
             data-parent-id=""
             x-data="{
                init() {
                    window.Sortable.create(this.$el, {
                        disabled: {{ $isAdminOrLeader ? 'false' : 'true' }},
                        group: {
                            name: 'kanban-cols',
                            pull: true,
                            put: (to, from, dragEl) => {
                                if (!dragEl.hasAttribute('data-column-id')) {
                                    return false;
                                }
                                const destParent = to.el.getAttribute('data-parent-id');
                                if (destParent !== '' && destParent !== null) {
                                    return !dragEl.hasAttribute('data-is-group');
                                }
                                return true;
                            }
                        },
                        draggable: '[data-column-id]',
                        handle: '.column-header-handle',
                        animation: 150,
                        ghostClass: 'opacity-20',
                        dragClass: 'rotate-2',
                        onEnd: (e) => {
                            const itemId = e.item.getAttribute('data-column-id');
                            if (!itemId) return;
                            let newParentId = e.to.getAttribute('data-parent-id') || null;
                            if (newParentId === '') newParentId = null;
                            const orderedIds = Array.from(e.to.children)
                                .filter(c => c.hasAttribute('data-column-id'))
                                .map(c => c.getAttribute('data-column-id'));
                            this.$wire.moveColumnAndReorder(itemId, newParentId, orderedIds);
                        }
                    });
                }
             }"
        >
            {{-- Kanban Columns --}}
            @php
                $parentColumns = collect($columns)->whereNull('parent_id')->sortBy('position');
            @endphp
            @foreach($parentColumns as $parentColumn)
        @php
            if (($parentColumn['is_hidden'] ?? false) && !$isAdminOrLeader) continue;
            
            $children = collect($columns)->where('parent_id', $parentColumn['id'])->sortBy('position');
            $hasChildren = $children->count() > 0;
        @endphp

        @if($hasChildren)
            {{-- GROUP WRAPPER: draggable at top level via the gray header (.column-header-handle) --}}
            <div class="group-col-wrapper grid grid-rows-[auto_minmax(0,1fr)] h-full flex-shrink-0 bg-transparent rounded-xl border border-zinc-200 dark:border-zinc-800"
                 wire:key="parent-{{ $parentColumn['id'] }}"
                 data-column-id="{{ $parentColumn['id'] }}"
                 data-is-group="true">
                
                {{-- Group Header: this is the drag handle for moving the ENTIRE group --}}
                <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800 rounded-t-xl flex items-center justify-between column-header-handle cursor-grab active:cursor-grabbing select-none relative">
                    <div class="flex items-center gap-2 sticky left-4 z-10 w-fit bg-zinc-50 dark:bg-zinc-800/50 pr-2">
                        <svg class="w-3.5 h-3.5 text-zinc-400" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm6 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm-6 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm6 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm-6 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm6 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>
                        <span class="font-bold text-zinc-800 dark:text-zinc-200 text-sm">{{ $parentColumn['title'] }}</span>
                    </div>
                    @if($isAdminOrLeader)
                        <div class="flex items-center gap-0.5 sticky right-4 z-10 bg-zinc-50 dark:bg-zinc-800/50 pl-2" @click.stop>
                            <flux:button wire:click="showAddSubColumnForm('{{ $parentColumn['id'] }}')" variant="ghost" size="sm" icon="plus" class="!px-1 !py-0.5 text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300" title="Tambah Sub Kolom" />
                            <flux:dropdown position="bottom end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="!px-1 !py-0.5 text-zinc-400 hover:text-zinc-600" />
                                <flux:menu>
                                    <flux:menu.item wire:click="editColumn('{{ $parentColumn['id'] }}')" icon="pencil">Edit Nama Grup</flux:menu.item>
                                    <flux:menu.item wire:click="showAddSubColumnForm('{{ $parentColumn['id'] }}')" icon="plus">Tambah Sub Kolom</flux:menu.item>
                                    <flux:menu.item wire:click="deleteColumn('{{ $parentColumn['id'] }}')" wire:confirm="Hapus grup kolom ini dan semua sub-kolom di dalamnya?" icon="trash" class="text-red-600 hover:bg-red-50">Hapus Grup Kolom</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    @endif
                </div>
                
                {{-- 
                    INNER SORTABLE: handles sub-columns within this group
                    Items: direct children with data-column-id
                    Can receive standalone columns dropped in (they become sub-columns)
                    Can pull sub-columns out (they become standalone at top level)
                --}}
                <div class="flex flex-nowrap min-w-[220px] p-2 gap-2 items-stretch"
                     data-parent-id="{{ $parentColumn['id'] }}"
                     x-data="{
                        init() {
                            window.Sortable.create(this.$el, {
                                disabled: {{ $isAdminOrLeader ? 'false' : 'true' }},
                                group: {
                                    name: 'kanban-cols',
                                    pull: true,
                                    put: (to, from, dragEl) => dragEl.hasAttribute('data-column-id') && !dragEl.hasAttribute('data-is-group')
                                },
                                draggable: '[data-column-id]',
                                handle: '.column-header-handle',
                                animation: 150,
                                ghostClass: 'opacity-20',
                                dragClass: 'rotate-2',
                                onEnd: (e) => {
                                    const itemId = e.item.getAttribute('data-column-id');
                                    if (!itemId) return;
                                    let newParentId = e.to.getAttribute('data-parent-id') || null;
                                    if (newParentId === '') newParentId = null;
                                    const orderedIds = Array.from(e.to.children)
                                        .filter(c => c.hasAttribute('data-column-id'))
                                        .map(c => c.getAttribute('data-column-id'));
                                    this.$wire.moveColumnAndReorder(itemId, newParentId, orderedIds);
                                }
                            });
                        }
                     }"
                >
                    @foreach($children as $childColumn)
                        @php
                            if (($childColumn['is_hidden'] ?? false) && !$isAdminOrLeader) continue;
                            $grandchildren = collect($columns)->where('parent_id', $childColumn['id'])->sortBy('position');
                            $hasGrandchildren = $grandchildren->count() > 0;
                        @endphp
                        {{-- IMPORTANT: wrapper div with data-column-id is required for SortableJS to track items --}}
                        <div data-column-id="{{ $childColumn['id'] }}"
                             wire:key="subcol-{{ $childColumn['id'] }}"
                             class="flex-shrink-0 h-full {{ $hasGrandchildren ? 'w-auto' : 'w-80' }}"
                             @if($hasGrandchildren) data-is-group="true" @endif>
                             
                            @if($hasGrandchildren)
                                <div class="group-col-wrapper grid grid-rows-[auto_minmax(0,1fr)] h-full bg-transparent rounded-xl border border-zinc-200 dark:border-zinc-800 min-w-full w-max">
                                    <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800 rounded-t-xl flex items-center justify-between column-header-handle cursor-grab active:cursor-grabbing select-none relative">
                                        <div class="flex items-center gap-2 sticky left-4 z-10 w-fit bg-zinc-50 dark:bg-zinc-800/50 pr-2">
                                            <svg class="w-3.5 h-3.5 text-zinc-400" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm6 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm-6 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm6 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm-6 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm6 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>
                                            <span class="font-bold text-zinc-800 dark:text-zinc-200 text-sm">{{ $childColumn['title'] }}</span>
                                        </div>
                                        @if($isAdminOrLeader)
                                            <div class="flex items-center gap-0.5 sticky right-4 z-10 bg-zinc-50 dark:bg-zinc-800/50 pl-2" @click.stop>
                                                <flux:button wire:click="showAddSubColumnForm('{{ $childColumn['id'] }}')" variant="ghost" size="sm" icon="plus" class="!px-1 !py-0.5 text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300" title="Tambah Sub Kolom" />
                                                <flux:dropdown position="bottom end">
                                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="!px-1 !py-0.5 text-zinc-400 hover:text-zinc-600" />
                                                    <flux:menu>
                                                        <flux:menu.item wire:click="editColumn('{{ $childColumn['id'] }}')" icon="pencil">Edit Nama Grup</flux:menu.item>
                                                        <flux:menu.item wire:click="showAddSubColumnForm('{{ $childColumn['id'] }}')" icon="plus">Tambah Sub Kolom</flux:menu.item>
                                                        <flux:menu.item wire:click="deleteColumn('{{ $childColumn['id'] }}')" wire:confirm="Hapus grup ini dan semua sub-kolom di dalamnya?" icon="trash" class="text-red-600 hover:bg-red-50">Hapus Grup Kolom</flux:menu.item>
                                                    </flux:menu>
                                                </flux:dropdown>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex flex-nowrap min-w-[220px] p-2 gap-2 items-stretch"
                                         data-parent-id="{{ $childColumn['id'] }}"
                                         x-data="{
                                            init() {
                                                window.Sortable.create(this.$el, {
                                                    disabled: {{ $isAdminOrLeader ? 'false' : 'true' }},
                                                    group: { name: 'kanban-cols', pull: true, put: (to, from, dragEl) => dragEl.hasAttribute('data-column-id') && !dragEl.hasAttribute('data-is-group') },
                                                    draggable: '[data-column-id]',
                                                    handle: '.column-header-handle',
                                                    animation: 150,
                                                    ghostClass: 'opacity-20',
                                                    dragClass: 'rotate-2',
                                                    onEnd: (e) => {
                                                        const itemId = e.item.getAttribute('data-column-id');
                                                        if (!itemId) return;
                                                        let newParentId = e.to.getAttribute('data-parent-id') || null;
                                                        if (newParentId === '') newParentId = null;
                                                        const orderedIds = Array.from(e.to.children).filter(c => c.hasAttribute('data-column-id')).map(c => c.getAttribute('data-column-id'));
                                                        this.$wire.moveColumnAndReorder(itemId, newParentId, orderedIds);
                                                    }
                                                });
                                            }
                                         }">
                                         @foreach($grandchildren as $grandchild)
                                             @php
                                                 if (($grandchild['is_hidden'] ?? false) && !$isAdminOrLeader) continue;
                                             @endphp
                                             <div data-column-id="{{ $grandchild['id'] }}" wire:key="subcol-{{ $grandchild['id'] }}" class="flex-shrink-0 h-full w-80">
                                                 @include('livewire.workspace.partials.kanban-column-content', [
                                                     'columnData' => $grandchild,
                                                     'statusKey' => $grandchild['id'],
                                                     'columnProjects' => collect($projects)->where('workspace_column_id', $grandchild['id']),
                                                     'isBacklogColumn' => false,
                                                     'canMakeGroup' => false
                                                 ])
                                             </div>
                                         @endforeach
                                         
                                         {{-- Inline form: Add Sub-sub Column --}}
                                         @if($addingSubColumnToParent == $childColumn['id'])
                                             <div class="flex-shrink-0 w-72 bg-white dark:bg-zinc-900 rounded-xl border border-indigo-200 dark:border-indigo-700/50 p-3 shadow-sm space-y-2 self-start mt-1">
                                                 <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-400">Tambah Sub-sub Kolom</p>
                                                 <flux:input wire:model="newSubColumnTitle" placeholder="Nama sub-sub kolom..." wire:keydown.enter="addSubColumn" autofocus />
                                                 <div class="flex items-center gap-2 justify-end">
                                                     <flux:button wire:click="cancelAddSubColumn" size="xs" variant="ghost">Batal</flux:button>
                                                     <flux:button wire:click="addSubColumn" size="xs" variant="primary">Simpan</flux:button>
                                                 </div>
                                             </div>
                                         @endif
                                    </div>
                                </div>
                            @else
                                @include('livewire.workspace.partials.kanban-column-content', [
                                    'columnData' => $childColumn,
                                    'statusKey' => $childColumn['id'],
                                    'columnProjects' => collect($projects)->where('workspace_column_id', $childColumn['id']),
                                    'isBacklogColumn' => false,
                                    'canMakeGroup' => true
                                ])
                            @endif
                        </div>
                    @endforeach
                    
                    {{-- Inline form: Add Sub Column (NOT a sortable item, no data-column-id) --}}
                    @if($addingSubColumnToParent == $parentColumn['id'])
                        <div class="flex-shrink-0 w-72 bg-white dark:bg-zinc-900 rounded-xl border border-indigo-200 dark:border-indigo-700/50 p-3 shadow-sm space-y-2 self-start mt-1">
                            <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-400">Tambah Sub Kolom</p>
                            <flux:input wire:model="newSubColumnTitle" placeholder="Nama sub kolom..." wire:keydown.enter="addSubColumn" autofocus />
                            <div class="flex items-center gap-2 justify-end">
                                <flux:button wire:click="cancelAddSubColumn" size="xs" variant="ghost">Batal</flux:button>
                                <flux:button wire:click="addSubColumn" size="xs" variant="primary">Tambah</flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            {{-- STANDALONE COLUMN: draggable at top level via x-kanban.column's .column-header-handle --}}
            <div class="standalone-col-wrapper flex-shrink-0 h-full w-80"
                 wire:key="col-{{ $parentColumn['id'] }}"
                 data-column-id="{{ $parentColumn['id'] }}">
                @include('livewire.workspace.partials.kanban-column-content', [
                    'columnData' => $parentColumn,
                    'statusKey' => $parentColumn['id'],
                    'columnProjects' => collect($projects)->where('workspace_column_id', $parentColumn['id']),
                    'isBacklogColumn' => $loop->first,
                    'canMakeGroup' => $isAdminOrLeader
                ])
            </div>
        @endif
    @endforeach

            {{-- Add Column --}}
            @if($isAdminOrLeader)
                <div class="flex-shrink-0 flex flex-col justify-start pt-2 h-full">
                    @if($isAddingColumn)
                        <div class="w-72 bg-white dark:bg-zinc-900 rounded-xl border border-indigo-200 dark:border-indigo-800/30 p-3 shadow-sm space-y-3">
                            <flux:input wire:model="newColumnTitle" placeholder="Nama kolom baru..." wire:keydown.enter="addNewColumn" autofocus />
                            
                            <div>
                                <p class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider mb-1.5">Warna Badge</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach(['slate' => '#64748b', 'red' => '#ef4444', 'orange' => '#f97316', 'amber' => '#f59e0b', 'green' => '#22c55e', 'cyan' => '#06b6d4', 'blue' => '#3b82f6', 'indigo' => '#6366f1', 'purple' => '#a855f7', 'pink' => '#ec4899'] as $name => $hex)
                                        <label class="cursor-pointer relative">
                                            <input type="radio" wire:model="newColumnColor" value="{{ $name }}" class="peer sr-only">
                                            <div class="w-5 h-5 rounded-full ring-2 ring-offset-1 ring-transparent peer-checked:ring-indigo-500 dark:ring-offset-zinc-900 transition-all hover:scale-110" style="background-color: {{ $hex }}"></div>
                                        </label>
                                    @endforeach
                                    
                                    <label class="relative flex items-center justify-center w-5 h-5 rounded-full ring-2 ring-offset-1 ring-transparent transition-all hover:scale-110 cursor-pointer overflow-hidden ml-1" 
                                           title="Warna Kustom"
                                           :class="$wire.newColumnColor.startsWith('#') ? 'ring-indigo-500 dark:ring-offset-zinc-900' : 'ring-zinc-200 dark:ring-zinc-700'">
                                        <div class="absolute inset-0 pointer-events-none" :class="$wire.newColumnColor.startsWith('#') ? 'hidden' : ''" style="background: conic-gradient(red, yellow, green, cyan, blue, magenta, red);"></div>
                                        <div class="absolute inset-0 pointer-events-none" x-show="$wire.newColumnColor.startsWith('#')" :style="{ backgroundColor: $wire.newColumnColor }" x-cloak></div>
                                        <input type="color" wire:model.live="newColumnColor" class="w-10 h-10 opacity-0 cursor-pointer absolute -top-2 -left-2">
                                    </label>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 justify-end">
                                <flux:button wire:click="$set('isAddingColumn', false)" size="xs" variant="ghost">Batal</flux:button>
                                <flux:button wire:click="addNewColumn" size="xs" variant="primary">Buat Kolom</flux:button>
                            </div>
                        </div>
                    @else
                        <flux:button wire:click="$set('isAddingColumn', true)" variant="ghost" class="h-auto py-3 px-4 !border-dashed border border-zinc-300 dark:border-zinc-700 rounded-xl text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 w-64">
                            <flux:icon.plus class="w-4 h-4 mr-2"/>
                            Tambah Kolom
                        </flux:button>
                    @endif
                </div>
            @endif
        </div>
    </x-slot:kanban_layout>

</x-kanban.board>

{{-- Task Detail Modal Component --}}
<livewire:workspace.task-detail-modal :workspace="$workspace" />

    {{-- Edit Column Modal --}}
    <flux:modal name="edit-column" class="md:max-w-xl" @close-edit-column-modal.window="$flux.modal('edit-column').close()">
        <div class="space-y-4 p-1">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Edit Kolom</h2>
            <flux:input wire:model="editingColumnTitle" label="Nama Kolom" placeholder="Nama Kolom" autofocus />
            
            <div class="pt-2">
                <flux:label>Warna Badge Kolom</flux:label>
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach(['slate' => '#64748b', 'red' => '#ef4444', 'orange' => '#f97316', 'amber' => '#f59e0b', 'green' => '#22c55e', 'cyan' => '#06b6d4', 'blue' => '#3b82f6', 'indigo' => '#6366f1', 'purple' => '#a855f7', 'pink' => '#ec4899'] as $name => $hex)
                        <label class="cursor-pointer relative" title="{{ ucfirst($name) }}">
                            <input type="radio" wire:model="editingColumnColor" value="{{ $name }}" class="peer sr-only">
                            <div class="w-6 h-6 rounded-full ring-2 ring-offset-2 ring-transparent peer-checked:ring-indigo-500 dark:ring-offset-zinc-900 transition-all hover:scale-110" style="background-color: {{ $hex }}"></div>
                        </label>
                    @endforeach
                    
                    <label class="relative flex items-center justify-center w-6 h-6 rounded-full ring-2 ring-offset-2 ring-transparent transition-all hover:scale-110 cursor-pointer overflow-hidden ml-1" 
                           title="Warna Kustom"
                           :class="$wire.editingColumnColor.startsWith('#') ? 'ring-indigo-500 dark:ring-offset-zinc-900' : 'ring-zinc-200 dark:ring-zinc-700'">
                        <div class="absolute inset-0 pointer-events-none" :class="$wire.editingColumnColor.startsWith('#') ? 'hidden' : ''" style="background: conic-gradient(red, yellow, green, cyan, blue, magenta, red);"></div>
                        <div class="absolute inset-0 pointer-events-none" x-show="$wire.editingColumnColor.startsWith('#')" :style="{ backgroundColor: $wire.editingColumnColor }" x-cloak></div>
                        <input type="color" wire:model.live="editingColumnColor" class="w-10 h-10 opacity-0 cursor-pointer absolute -top-2 -left-2">
                    </label>
                </div>
            </div>
            
            @php
                $isGroupCol = false;
                if ($editingColumnId) {
                    $col = collect($columns)->firstWhere('id', $editingColumnId);
                    if ($col) {
                        // A group column has no parent but has children
                        $isGroupCol = $col['parent_id'] === null && collect($columns)->where('parent_id', $editingColumnId)->count() > 0;
                    }
                }
            @endphp
            
            @if(!$isGroupCol)
            <div class="pt-4 mt-4 border-t border-zinc-200 dark:border-zinc-700">
                <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Tipe / Sifat Kolom</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ $editingColumnType == 'normal' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <input type="radio" wire:model="editingColumnType" value="normal" class="mt-1">
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Normal</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Bebas pindah dari/ke sini.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ $editingColumnType == 'backlog' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <input type="radio" wire:model="editingColumnType" value="backlog" class="mt-1">
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Backlog</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Hanya Leader yang bisa narik.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ $editingColumnType == 'review' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <input type="radio" wire:model="editingColumnType" value="review" class="mt-1">
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Review</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Butuh Approve Leader.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ $editingColumnType == 'done' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <input type="radio" wire:model="editingColumnType" value="done" class="mt-1">
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Done</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Selesai, terkunci dari anggota.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ $editingColumnType == 'arsip' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <input type="radio" wire:model="editingColumnType" value="arsip" class="mt-1">
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Arsip</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Ruang penyimpanan kartu lama.</p>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="mt-4 flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Sembunyikan Kolom</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Anggota tidak akan bisa melihat kolom ini.</p>
                </div>
                <flux:switch wire:model="editingColumnIsHidden" />
            </div>
            @endif
            
            <div class="flex justify-end gap-2 mt-4">
                <flux:button type="button" @click="$flux.modal('edit-column').close()" variant="ghost">Batal</flux:button>
                <flux:button wire:click="updateColumn" variant="primary">Simpan</flux:button>
            </div>
        </div>
    </flux:modal>
    
    {{-- Delete Column Confirmation Modal --}}
    <flux:modal name="delete-column" class="md:max-w-md" @open-delete-column-modal.window="$flux.modal('delete-column').show()" @close-delete-column-modal.window="$flux.modal('delete-column').close()">
        <div class="space-y-4 p-1">
            <div class="flex items-center gap-3 text-amber-600 dark:text-amber-500 mb-2">
                <flux:icon.exclamation-triangle class="w-6 h-6" />
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Kolom Berisi Task</h2>
            </div>
            
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Ada <strong>{{ $tasksCountToMove }} task</strong> di dalam kolom (atau sub-kolom) ini. Pilih kolom tujuan untuk memindahkan task-task tersebut sebelum kolom ini dihapus.
            </p>
            
            <div class="pt-2">
                <flux:select wire:model="fallbackColumnId" label="Pindahkan Task ke:">
                    @php
                        // Filter out columns being deleted and parent groups
                        $subCols = collect($columns)->where('parent_id', $columnToDeleteId)->pluck('id')->toArray();
                        $subSubCols = collect($columns)->whereIn('parent_id', $subCols)->pluck('id')->toArray();
                        $deletedIds = array_merge([$columnToDeleteId], $subCols, $subSubCols);
                        
                        $availableCols = collect($columns)->whereNotIn('id', $deletedIds);
                    @endphp
                    
                    @foreach($availableCols->sortBy('position') as $col)
                        @if($availableCols->where('parent_id', $col['id'])->count() == 0)
                            <option value="{{ $col['id'] }}">{{ $col['title'] }}</option>
                        @endif
                    @endforeach
                </flux:select>
            </div>
            
            <div class="flex justify-end gap-2 mt-6">
                <flux:button type="button" @click="$flux.modal('delete-column').close()" variant="ghost">Batal</flux:button>
                <flux:button wire:click="confirmDeleteColumn" variant="danger">Pindahkan & Hapus</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Members Management Modal --}}
    <flux:modal wire:model="showMembersModal" class="w-full max-w-2xl">
        <div class="p-4 space-y-6 max-h-[85vh] overflow-y-auto custom-scrollbar">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-700 pb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Anggota Workspace</h2>
            </div>

            {{-- Invite Form --}}
            @if($this->isAdminOrLeader)
                <div class="bg-indigo-50/50 dark:bg-indigo-900/10 p-4 rounded-xl border border-indigo-100 dark:border-indigo-800/30">
                    <h3 class="text-sm font-medium text-indigo-900 dark:text-indigo-200 mb-3">Undang Anggota Baru</h3>
                    <div class="relative" x-data="{ query: @entangle('searchUserQuery') }">
                        <flux:input wire:model.live.debounce.300ms="searchUserQuery" placeholder="Cari berdasarkan nama atau email..." icon="magnifying-glass" />
                        
                        @if(count($searchResults) > 0 || trim($searchUserQuery) !== '')
                            <div class="mt-2 w-full bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-zinc-200 dark:border-zinc-700 max-h-60 overflow-y-auto custom-scrollbar">
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
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-y-auto max-h-[50vh] custom-scrollbar">
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
                                @if($this->isAdminOrLeader && $workspace->owner_id != $member->id)
                                    <flux:select wire:change="updateRole({{ $member->id }}, $event.target.value)" size="sm" class="w-32">
                                        <option value="admin" {{ $member->pivot->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                        <option value="leader" {{ $member->pivot->role === 'leader' ? 'selected' : '' }}>Leader</option>
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

    {{-- OKR Modal --}}
    <flux:modal wire:model="showOkrModal" class="w-full md:max-w-6xl">
        <div class="pt-2 max-h-[85vh] overflow-y-auto custom-scrollbar">
            <livewire:workspace.okr :workspace="$workspace" :is-admin-or-leader="$isAdminOrLeader" />
        </div>
    </flux:modal>

    {{-- KPI Modal --}}
    <flux:modal wire:model="showKpiModal" class="w-full md:max-w-6xl">
        <div class="pt-2 max-h-[85vh] overflow-y-auto custom-scrollbar">
            <livewire:workspace.kpi :workspace="$workspace" :is-admin-or-leader="$isAdminOrLeader" />
        </div>
    </flux:modal>
    {{-- Custom Workspace Action Toast --}}
    <div x-data="{
        show: false,
        user: '',
        avatar: '',
        message: '',
        timeout: null,
        showNotification(data) {
            this.user = data.user;
            this.avatar = data.avatar;
            // Format pesan menjadi seolah user berbicara
            let rawMsg = data.message.trim();
            // Ubah huruf pertama menjadi huruf kecil agar nyambung dengan 'Saya baru saja...'
            if (rawMsg.length > 0) {
                rawMsg = rawMsg.charAt(0).toLowerCase() + rawMsg.slice(1);
            }
            this.message = 'Saya baru saja ' + rawMsg;
            
            this.show = true;
            clearTimeout(this.timeout);
            this.timeout = setTimeout(() => { this.show = false; }, 4000);
        }
    }" 
    @workspace-action-toast.window="showNotification($event.detail)"
    class="fixed bottom-6 right-6 z-50"
    style="display: none;"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <div class="pointer-events-auto w-[280px] sm:w-80 overflow-hidden rounded-xl bg-white dark:bg-zinc-800 shadow-lg ring-1 ring-black ring-opacity-5 dark:ring-white/10">
            <div class="p-3">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 pt-0.5">
                        <template x-if="avatar">
                            <img class="h-10 w-10 rounded-full object-cover shadow-sm ring-2 ring-indigo-50 dark:ring-indigo-900/30" :src="avatar" alt="">
                        </template>
                        <template x-if="!avatar">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30 ring-2 ring-indigo-50 dark:ring-indigo-900/30">
                                <span class="text-sm font-bold text-indigo-700 dark:text-indigo-300" x-text="user.charAt(0).toUpperCase()"></span>
                            </div>
                        </template>
                    </div>
                    <div class="ml-1 flex-1">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100" x-text="user"></p>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400" x-text="message"></p>
                    </div>
                    <div class="ml-4 flex flex-shrink-0">
                        <button @click="show = false" type="button" class="inline-flex rounded-md bg-white dark:bg-zinc-800 text-zinc-400 hover:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <span class="sr-only">Close</span>
                            <flux:icon.x-mark class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    
    {{-- Info / Guidelines Modal --}}
    <flux:modal wire:model="showInfoModal" class="w-full max-w-2xl">
        <div class="space-y-6">
            <div class="flex items-center gap-3 border-b border-zinc-200 dark:border-zinc-700 pb-4">
                <div class="p-2 bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 rounded-lg">
                    <flux:icon.information-circle class="w-6 h-6" />
                </div>
                <div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">Panduan & Info Workspace</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Deskripsi, Aturan, dan Job Deskripsi Tim.</p>
                </div>
            </div>

            <div class="prose prose-sm dark:prose-invert max-w-none prose-indigo p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700/50 rich-text-content">
                <style>
                    .rich-text-content h1 { font-size: 1.5em; font-weight: 700; margin-top: 1em; margin-bottom: 0.5em; }
                    .rich-text-content h2 { font-size: 1.25em; font-weight: 600; margin-top: 1em; margin-bottom: 0.5em; }
                    .rich-text-content h3 { font-size: 1.125em; font-weight: 600; margin-top: 1em; margin-bottom: 0.5em; }
                    .rich-text-content p { margin-bottom: 0.75em; }
                    .rich-text-content ul { list-style-type: disc; padding-left: 1.5em; margin-bottom: 0.75em; }
                    .rich-text-content ol { list-style-type: decimal; padding-left: 1.5em; margin-bottom: 0.75em; }
                    .rich-text-content blockquote { border-left: 4px solid #e5e7eb; padding-left: 1em; color: #6b7280; font-style: italic; margin-bottom: 0.75em; }
                    .dark .rich-text-content blockquote { border-color: #374151; color: #9ca3af; }
                    .rich-text-content a { color: #3b82f6; text-decoration: underline; }
                    .rich-text-content strong { font-weight: 700; }
                    .rich-text-content *:last-child { margin-bottom: 0; }
                </style>
                @if(!empty($workspace->description))
                    {!! $workspace->description !!}
                @else
                    <div class="text-center py-6 opacity-50">
                        <flux:icon.document-text class="w-10 h-10 mx-auto mb-2 opacity-50" />
                        <p>Belum ada deskripsi untuk workspace ini.</p>
                        @if($isAdminOrLeader)
                            <p class="text-xs mt-1">Anda bisa menambahkannya sekarang.</p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex justify-between pt-2">
                <div>
                    @if($isAdminOrLeader)
                        <flux:button @click="$wire.openWorkspaceEditor(); showWorkspaceEditor = true" variant="subtle" icon="pencil">Edit Panduan</flux:button>
                    @endif
                </div>
                <flux:button wire:click="$set('showInfoModal', false)" variant="primary">Tutup</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Rich Text Editor Modal for Workspace Description --}}
    <x-rich-editor-modal 
        title="Edit Panduan Workspace"
        subtitle="Mode Lengkap"
        wireModel="tempWorkspaceDescription"
        onSave="await $wire.saveWorkspaceDescription(); showWorkspaceEditor = false;"
        onCancel="showWorkspaceEditor = false; $wire.set('showInfoModal', true);"
        showVariable="showWorkspaceEditor"
    />
</div>
</div>



