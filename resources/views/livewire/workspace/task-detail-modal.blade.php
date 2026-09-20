<?php

use App\Models\User;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Models\TaskSubtask;
use Modules\Workspace\Models\TaskComment;
use Modules\Workspace\Models\TaskAttachment;
use Modules\Workspace\Models\TaskActivity;
use Modules\Workspace\Models\TaskLabel;
use Illuminate\Support\Carbon;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component {
    use WithFileUploads;

    public $workspace;
    public $selectedProject = null;
    
    // State
    public string $newSubtaskTitle = '';
    public string $newComment = '';
    public string $tempDescription = '';
    public $replyToCommentId = null;
    public $attachmentFile = null;
    public $commentAttachmentFile = null;
    public array $availableLabels = [];
    public array $columns = []; // for list name
    public array $workspaceUsers = [];
    public bool $showProjectModal = false;
    public bool $showRichEditorModal = false;

    // Triggered when opened
    #[\Livewire\Attributes\On('open-task-modal')]
    public function openModal($taskId, $columns) {
        $this->columns = $columns;
        $this->loadTask($taskId);
        $this->availableLabels = TaskLabel::all()->toArray();
        $this->newSubtaskTitle = '';
        $this->newComment = '';
        $this->showProjectModal = true;
        $this->dispatch('task-loaded');
    }

    public function loadTask($taskId) {
        // Normalize inconsistent states before loading
        $this->normalizeSubtaskStates($taskId);

        $task = Task::with([
            'assignees', 
            'labels', 
            'subtasks' => function($q) {
                $q->with('children.children')->whereNull('parent_id')->orderBy('created_at', 'asc');
            }, 
            'comments.user', 
            'comments.parent.user',
            'attachments', 
            'timeLogs',
            'activities.user'
        ])->find($taskId);

        if ($task) {
            $this->selectedProject = $task->toArray();
            
            // Sort activities newest first
            if (isset($this->selectedProject['activities'])) {
                usort($this->selectedProject['activities'], function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
            }
        } else {
            $this->selectedProject = null;
        }

        // Load users for mentions
        $this->workspaceUsers = \App\Models\User::select('id', 'name', 'email')->get()->toArray();
    }

    public function formatCommentContent($content) {
        if (empty($content)) return '';
        $formatted = htmlspecialchars($content);
        
        // Find format @[User Name](user:id)
        $pattern = '/@\[([^\]]+)\]\(user:\d+\)/';
        $formatted = preg_replace($pattern, '<span class="text-indigo-600 dark:text-indigo-400 font-semibold bg-indigo-50 dark:bg-indigo-500/10 px-1 py-0.5 rounded cursor-pointer hover:underline">@$1</span>', $formatted);

        // Find format [attachment:path|name|isImage]
        $attachmentPattern = '/\[attachment:([^|]+)\|([^|]+)\|([01])\]/';
        $formatted = preg_replace_callback($attachmentPattern, function($matches) {
            $path = htmlspecialchars($matches[1], ENT_QUOTES);
            $name = htmlspecialchars($matches[2], ENT_QUOTES);
            $isImage = $matches[3] === '1';
            $url = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
            
            if ($isImage) {
                return '<div class="mt-2 mb-1"><a href="'.$url.'" target="_blank" class="block w-full max-w-sm"><img loading="lazy" src="'.$url.'" alt="'.$name.'" class="w-full h-auto rounded-lg border border-zinc-200 dark:border-zinc-700 object-cover shadow-sm hover:opacity-90 transition-opacity"></a></div>';
            } else {
                return '<div class="mt-2 mb-1 flex items-center gap-3 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors w-full max-w-sm group"><div class="p-2 bg-white dark:bg-zinc-700 rounded-lg shadow-sm group-hover:shadow text-indigo-500 dark:text-indigo-400"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg></div><a href="'.$url.'" target="_blank" class="truncate font-semibold text-sm text-zinc-700 dark:text-zinc-200 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors flex-1" title="'.$name.'">'.$name.'</a></div>';
            }
        }, $formatted);

        return nl2br($formatted);
    }

    private function normalizeSubtaskStates($taskId) {
        $roots = TaskSubtask::where('task_id', $taskId)->whereNull('parent_id')->get();
        foreach ($roots as $root) {
            $this->normalizeSubtask($root);
        }
    }

    private function normalizeSubtask($subtask) {
        $children = TaskSubtask::where('parent_id', $subtask->id)->get();
        if ($children->count() === 0) return; // leaf node

        // Normalize children first (bottom-up)
        foreach ($children as $child) {
            $this->normalizeSubtask($child);
        }

        // Re-fetch to get updated states after children normalized
        $children = TaskSubtask::where('parent_id', $subtask->id)->get();
        $allCompleted = $children->every(fn($c) => $c->is_completed);

        if ($subtask->is_completed !== $allCompleted) {
            $subtask->update(['is_completed' => $allCompleted]);
        }
    }

    public function recordActivity($taskId, $description) {
        TaskActivity::create([
            'task_id' => $taskId,
            'user_id' => auth()->id(),
            'action' => 'update',
            'description' => $description
        ]);
    }

    public function updateProjectField($field, $value) {
        if (!$this->selectedProject) return;
        $task = Task::find($this->selectedProject['id']);
        if ($task) {
            $task->update([$field => $value ?: null]);
            $this->recordActivity($task->id, "Memperbarui {$field} tugas");
            $this->loadTask($task->id);
            $this->dispatch('task-updated');
        }
    }

    public function copyTask() {
        if (!$this->selectedProject) return;
        $task = Task::with(['labels', 'assignees'])->find($this->selectedProject['id']);
        if ($task) {
            $newTask = $task->replicate();
            $newTask->title = $newTask->title . ' (Copy)';
            $newTask->save();

            // Copy labels
            $newTask->labels()->sync($task->labels->pluck('id'));
            
            // Copy assignees
            $newTask->assignees()->sync($task->assignees->pluck('id'));

            $this->dispatch('task-updated');
            $this->showProjectModal = false; 
        }
    }

    public function saveRichDescription() {
        if ($this->selectedProject) {
            $this->updateProjectField('description', $this->tempDescription);
            $this->showRichEditorModal = false;
        }
    }

    public function openRichEditor($currentDescription) {
        $this->tempDescription = $currentDescription;
        $this->showRichEditorModal = true;
    }

    public function addComment() {
        if (!$this->selectedProject) return;
        if (empty(trim($this->newComment)) && !$this->commentAttachmentFile) return;
        
        $task = Task::find($this->selectedProject['id']);
        if ($task) {
            // Parse mentions @username to @[username](user:id)
            $parsedContent = $this->newComment;
            foreach ($this->workspaceUsers as $u) {
                $mention = '@' . $u['name'];
                $tag = '@[' . $u['name'] . '](user:' . $u['id'] . ')';
                $parsedContent = str_ireplace($mention, $tag, $parsedContent);
            }

            // Handle attachment
            if ($this->commentAttachmentFile) {
                $path = $this->commentAttachmentFile->store('attachments', 'public');
                
                // Add to main Task Attachments
                TaskAttachment::create([
                    'task_id' => $task->id,
                    'user_id' => auth()->id(),
                    'file_name' => $this->commentAttachmentFile->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $this->commentAttachmentFile->getMimeType(),
                    'file_size' => $this->commentAttachmentFile->getSize()
                ]);

                // Prepend to comment content so file appears above text
                $mime = $this->commentAttachmentFile->getMimeType();
                $isImage = str_starts_with($mime, 'image/') ? '1' : '0';
                $parsedContent = "[attachment:{$path}|{$this->commentAttachmentFile->getClientOriginalName()}|{$isImage}]\n\n" . $parsedContent;
            }

            TaskComment::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'parent_id' => $this->replyToCommentId,
                'content' => trim($parsedContent)
            ]);
            
            $this->recordActivity($task->id, 'Menambahkan komentar baru');
            
            $this->newComment = '';
            $this->replyToCommentId = null;
            $this->commentAttachmentFile = null;
            $this->loadTask($task->id);
            $this->dispatch('task-updated');
        }
    }

    public function cancelReply() {
        $this->replyToCommentId = null;
    }

    public function editComment($commentId, $newContent) {
        if (empty(trim($newContent))) return;

        $comment = TaskComment::find($commentId);
        if (!$comment) return;

        // Only the author can edit
        if ($comment->user_id !== auth()->id()) return;

        $comment->update(['content' => trim($newContent)]);
        $this->recordActivity($comment->task_id, 'Mengedit komentar');
        $this->loadTask($comment->task_id);
    }

    public function deleteComment($commentId) {
        $comment = TaskComment::find($commentId);
        if (!$comment) return;

        // Only the author can delete
        if ($comment->user_id !== auth()->id()) return;

        $taskId = $comment->task_id;
        $comment->delete();
        $this->recordActivity($taskId, 'Menghapus komentar');
        $this->loadTask($taskId);
    }

    public function uploadAttachment() {
        if (!$this->attachmentFile || !$this->selectedProject) return;

        $task = Task::find($this->selectedProject['id']);
        if ($task) {
            $path = $this->attachmentFile->store('attachments', 'public');
            
            TaskAttachment::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'file_path' => $path,
                'file_name' => $this->attachmentFile->getClientOriginalName(),
                'file_type' => $this->attachmentFile->getMimeType(),
                'file_size' => $this->attachmentFile->getSize()
            ]);

            $this->recordActivity($task->id, "Mengunggah lampiran: " . $this->attachmentFile->getClientOriginalName());
            $this->attachmentFile = null;
            $this->loadTask($task->id);
            $this->dispatch('task-updated');
        }
    }

    public function toggleAssignee($userId) {
        if (!$this->selectedProject) return;
        $task = Task::find($this->selectedProject['id']);
        if ($task) {
            $task->assignees()->toggle($userId);
            $user = User::find($userId);
            $action = $task->assignees()->where('user_id', $userId)->exists() ? 'Menugaskan' : 'Melepas penugasan';
            $this->recordActivity($task->id, "{$action} {$user->name}");
            $this->loadTask($task->id);
            $this->dispatch('task-updated');
        }
    }

    public function toggleLabel($labelId) {
        if (!$this->selectedProject) return;
        $task = Task::find($this->selectedProject['id']);
        if ($task) {
            $task->labels()->toggle($labelId);
            $this->loadTask($task->id);
            $this->dispatch('task-updated');
        }
    }

    public function toggleSubtask($subtaskId) {
        $subtask = TaskSubtask::find($subtaskId);
        if ($subtask) {
            $newState = !$subtask->is_completed;
            $subtask->update(['is_completed' => $newState]);
            
            // 1. Cascade Down: Update all descendants
            $this->cascadeDownSubtaskState($subtask, $newState);
            
            // 2. Cascade Up: Update ancestors based on children's state
            if ($subtask->parent_id) {
                $this->cascadeUpSubtaskState($subtask->parent_id);
            }
            
            $status = $newState ? 'Menyelesaikan' : 'Membatalkan selesai';
            $this->recordActivity($subtask->task_id, "{$status} subtask: {$subtask->title}");
            $this->loadTask($subtask->task_id);
            $this->dispatch('task-updated');
        }
    }

    private function cascadeDownSubtaskState($subtask, $state) {
        $children = TaskSubtask::where('parent_id', $subtask->id)->get();
        foreach ($children as $child) {
            if ($child->is_completed !== $state) {
                $child->update(['is_completed' => $state]);
            }
            $this->cascadeDownSubtaskState($child, $state);
        }
    }

    private function cascadeUpSubtaskState($parentId) {
        $parent = TaskSubtask::find($parentId);
        if (!$parent) return;

        $totalChildren = TaskSubtask::where('parent_id', $parent->id)->count();
        $completedChildren = TaskSubtask::where('parent_id', $parent->id)->where('is_completed', true)->count();
        
        $shouldBeCompleted = ($totalChildren > 0 && $totalChildren === $completedChildren);
        
        if ($parent->is_completed !== $shouldBeCompleted) {
            $parent->update(['is_completed' => $shouldBeCompleted]);
            
            if ($parent->parent_id) {
                $this->cascadeUpSubtaskState($parent->parent_id);
            }
        }
    }

    public function addSubtask($parentId = null, $title = null) {
        $title = $title ?: $this->newSubtaskTitle;
        if (!$this->selectedProject || empty(trim($title))) return;
        
        $task = Task::find($this->selectedProject['id']);
        if ($task) {
            TaskSubtask::create([
                'task_id' => $task->id,
                'title' => trim($title),
                'parent_id' => $parentId,
                'is_completed' => false,
                'requires_input' => false
            ]);
            $this->recordActivity($task->id, "Menambahkan subtask: " . trim($title));
            if (!$parentId) $this->newSubtaskTitle = '';
            $this->loadTask($task->id);
            $this->dispatch('task-updated');
        }
    }

    public function deleteSubtask($subtaskId) {
        $subtask = TaskSubtask::find($subtaskId);
        if ($subtask) {
            $taskId = $subtask->task_id;
            $this->recordActivity($taskId, "Menghapus subtask: {$subtask->title}");
            $subtask->delete();
            $this->loadTask($taskId);
            $this->dispatch('task-updated');
        }
    }

    public function deleteChecklist() {
        if (!$this->selectedProject) return;
        $taskId = $this->selectedProject['id'];
        
        // Delete all subtasks for this task
        TaskSubtask::where('task_id', $taskId)->delete();
        
        $this->recordActivity($taskId, "Menghapus seluruh checklist");
        $this->loadTask($taskId);
        $this->dispatch('task-updated');
    }

    public function getSubtaskProgress() {
        if (!$this->selectedProject || empty($this->selectedProject['subtasks'])) return 0;
        
        $stats = $this->calculateProgress($this->selectedProject['subtasks']);
        if ($stats['total'] === 0) return 0;
        
        return round(($stats['completed'] / $stats['total']) * 100);
    }
    
    private function calculateProgress($subtasks) {
        $total = 0;
        $completed = 0;
        
        foreach ($subtasks as $subtask) {
            $total++;
            if ($subtask['is_completed']) {
                $completed++;
            }
            if (!empty($subtask['children'])) {
                $childStats = $this->calculateProgress($subtask['children']);
                $total += $childStats['total'];
                $completed += $childStats['completed'];
            }
        }
        
        return ['total' => $total, 'completed' => $completed];
    }

    public function saveSubtaskValue($subtaskId, $value) {
        $subtask = TaskSubtask::find($subtaskId);
        if ($subtask) {
            $subtask->update(['input_value' => $value]);
            $this->recordActivity($subtask->task_id, "Menyimpan nilai input untuk subtask: {$subtask->title}");
            $this->loadTask($subtask->task_id);
            $this->dispatch('task-updated');
        }
    }

    public function createLabel($name, $color) {
        $name = trim($name);
        if (empty($name)) return;
        
        $label = TaskLabel::create([
            'name' => $name,
            'color' => $color,
        ]);
        
        if ($this->selectedProject) {
            $project = Task::find($this->selectedProject['id']);
            if ($project) {
                $project->labels()->attach($label->id);
                $this->loadTask($project->id);
                $this->dispatch('task-updated');
            }
        }
        
        $this->availableLabels = TaskLabel::all()->toArray();
    }
}; ?>

<div x-data="{ showEditor: false }" @close-rich-editor.window="showEditor = false; $wire.set('showProjectModal', true)">
    <flux:modal wire:model="showProjectModal" name="task-detail" class="w-full max-w-5xl p-0 max-sm:!m-0 max-sm:!w-[100vw] max-sm:!max-w-[100vw] max-sm:!h-[100dvh] max-sm:!max-h-[100dvh] max-sm:!rounded-none kanban-modal">
        
        <div x-data="{ isLoading: false }" @task-loading.window="isLoading = true" @task-loaded.window="isLoading = false" class="w-full">
        {{-- SKELETON UI --}}
        <div x-show="isLoading" class="w-full">
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
        <div x-show="!isLoading" class="w-full">
            @if($selectedProject)
            <div x-data="{ sidebarOpen: true, isFullscreen: false }" 
                 x-init="$watch('isFullscreen', val => document.body.classList.toggle('kanban-fullscreen', val)); if(isFullscreen) document.body.classList.add('kanban-fullscreen');"
                 class="flex flex-col md:flex-row gap-0 transition-all duration-500 ease-out" 
                 x-bind:class="isFullscreen ? '' : 'max-sm:h-[100dvh]'">
                
<style>
    body.kanban-fullscreen dialog:has(.kanban-modal),
    body.kanban-fullscreen dialog.kanban-modal,
    body.kanban-fullscreen .kanban-modal {
        max-width: 100% !important;
        width: 100% !important;
        max-height: 100% !important;
        height: 100% !important;
        border-radius: 0 !important;
    }
    
    @media (max-width: 640px) {
        dialog:has(.kanban-modal),
        dialog.kanban-modal,
        .kanban-modal {
            max-width: 100% !important;
            width: 100% !important;
            max-height: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            border-radius: 0 !important;
        }
    }
</style>

                @php
                    $isOwner = isset($workspace['owner_id']) && auth()->id() === $workspace['owner_id'];
                @endphp

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
                            <div class="flex-1" x-data="{ editingTitle: false, title: '{{ addslashes($selectedProject['title'] ?? '') }}' }">
                                <div x-show="!editingTitle" @if($isOwner) @click="editingTitle = true; $nextTick(() => $refs.titleInput.focus())" class="cursor-pointer group/title rounded-lg -ml-2 p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors" @else class="group/title rounded-lg -ml-2 p-2" @endif>
                                    <h2 class="text-2xl font-extrabold text-zinc-900 dark:text-white tracking-tight leading-tight">
                                        {{ $selectedProject['title'] }}
                                    </h2>
                                </div>
                                <div x-show="editingTitle" class="mb-1.5 -ml-2 px-2 pt-2" @click.away="if(title !== '{{ addslashes($selectedProject['title'] ?? '') }}') $wire.updateProjectField('title', title); editingTitle = false">
                                    <input type="text" x-ref="titleInput" x-model="title" @keydown.enter="if(title !== '{{ addslashes($selectedProject['title'] ?? '') }}') $wire.updateProjectField('title', title); editingTitle = false" class="w-full text-2xl font-extrabold text-zinc-900 dark:text-white tracking-tight leading-tight bg-white dark:bg-zinc-900 border-2 border-indigo-500 rounded-lg px-2 py-1 focus:outline-none focus:ring-4 focus:ring-indigo-500/20" />
                                </div>
                                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5 mt-1">
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
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 border-2 border-white dark:border-zinc-900 shadow-sm flex items-center justify-center text-sm font-bold text-indigo-700 dark:text-indigo-300 hover:-translate-y-1 hover:shadow-md transition-all duration-300 cursor-pointer" title="{{ $assignee['name'] }}">
                                        {{ substr($assignee['name'], 0, 1) }}
                                    </div>
                                @empty
                                    <span class="text-sm text-zinc-400 italic">Unassigned</span>
                                @endforelse
                                @if($isOwner)
                                <flux:dropdown>
                                    <button class="w-9 h-9 rounded-full bg-zinc-100/80 hover:bg-zinc-200 dark:bg-zinc-800/80 dark:hover:bg-zinc-700 border-2 border-dashed border-zinc-300 dark:border-zinc-600 flex items-center justify-center text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 hover:scale-105 hover:shadow-sm transition-all duration-300">
                                        <flux:icon.plus class="w-4 h-4" />
                                    </button>
                                    <flux:menu class="max-h-64 overflow-y-auto w-64">
                                        <flux:menu.heading>Assign Members</flux:menu.heading>
                                        @if(isset($workspace['users']))
                                            @foreach($workspace['users'] as $user)
                                                <flux:menu.checkbox wire:click="toggleAssignee({{ $user['id'] }})" :checked="collect($selectedProject['assignees'] ?? [])->pluck('id')->contains($user['id'])">
                                                    {{ $user['name'] }}
                                                </flux:menu.checkbox>
                                            @endforeach
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                                @endif
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
                                @if($isOwner)
                                <flux:dropdown>
                                    <button class="h-8 px-2.5 rounded-lg bg-zinc-100/80 hover:bg-zinc-200 dark:bg-zinc-800/80 dark:hover:bg-zinc-700 border-2 border-dashed border-zinc-300 dark:border-zinc-600 flex items-center justify-center text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 hover:scale-105 hover:shadow-sm transition-all duration-300">
                                        <flux:icon.plus class="w-4 h-4" />
                                    </button>
                                    <flux:menu class="max-h-80 overflow-y-auto w-64">
                                        <flux:menu.heading>Assign Labels</flux:menu.heading>
                                        @foreach($availableLabels as $label)
                                            <flux:menu.checkbox wire:click="toggleLabel({{ $label['id'] }})" :checked="collect($selectedProject['labels'] ?? [])->pluck('id')->contains($label['id'])">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-3 h-3 rounded-full shadow-sm" style="background-color: {{ $label['color'] }}"></div>
                                                    {{ $label['name'] }}
                                                </div>
                                            </flux:menu.checkbox>
                                        @endforeach
                                        <flux:menu.separator />
                                        <flux:menu.item @click="$flux.modal('create-label-modal').show()">
                                            <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400 font-medium w-full">
                                                <flux:icon.plus class="w-4 h-4" />
                                                Buat Label Baru
                                            </div>
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                                @endif
                            </div>
                        </div>

                        {{-- Due Date --}}
                        <div class="space-y-3">
                            <h3 class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">Due Date</h3>
                            <div class="relative overflow-hidden flex items-center gap-2 bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 px-3 py-1.5 rounded-lg hover:bg-zinc-200/80 dark:hover:bg-zinc-700 hover:shadow-sm transition-all duration-300 @if($isOwner) cursor-pointer group @endif shadow-sm">
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
                                @if($isOwner)
                                <flux:icon.chevron-down class="w-3 h-3 text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors ml-1" />
                                <input type="date" onclick="this.showPicker()" @change="$wire.updateProjectField('due_date', $event.target.value)" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- 3. Description --}}
                    <div class="flex items-start gap-4">
                        <div class="mt-1 p-2 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 shadow-sm">
                            <flux:icon.bars-3-bottom-left class="w-5 h-5" />
                        </div>
                        <div class="flex-1 space-y-3">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Description</h3>
                                @if($isOwner)
                                @endif
                            </div>
                            <div class="bg-zinc-50/50 dark:bg-zinc-900/50 @if($isOwner) hover:bg-white dark:hover:bg-zinc-800/80 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-md cursor-pointer @endif p-5 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 transition-all duration-300 min-h-[100px] group shadow-sm"
                                 @if($isOwner) @click="$wire.set('showProjectModal', false); $wire.set('tempDescription', @js($selectedProject['description'] ?? '')); showEditor = true" @endif>
                                <div class="min-h-[80px]">
                                    <div class="rich-text-content text-base text-zinc-700 dark:text-zinc-200 leading-relaxed group-hover:text-zinc-900 dark:group-hover:text-white transition-colors max-w-none">
                                        <style>
                                            .rich-text-content h1 { font-size: 1.5em; font-weight: 700; margin-top: 1em; margin-bottom: 0.5em; }
                                            .rich-text-content h2 { font-size: 1.25em; font-weight: 600; margin-top: 1em; margin-bottom: 0.5em; }
                                            .rich-text-content h3 { font-size: 1.125em; font-weight: 600; margin-top: 1em; margin-bottom: 0.5em; }
                                            .rich-text-content p { margin-bottom: 0.75em; }
                                            .rich-text-content ul { list-style-type: disc; padding-left: 1.5em; margin-bottom: 0.75em; }
                                            .rich-text-content ol { list-style-type: decimal; padding-left: 1.5em; margin-bottom: 0.75em; }
                                            .rich-text-content blockquote { border-left: 4px solid #e5e7eb; padding-left: 1em; color: #6b7280; font-style: italic; margin-bottom: 0.75em; }
                                            .rich-text-content a { color: #3b82f6; text-decoration: underline; }
                                            .rich-text-content strong { font-weight: 700; }
                                            .rich-text-content img, .rich-text-content iframe, .rich-text-content video { display: inline-block; max-width: 100%; }
                                            .rich-text-content *:last-child { margin-bottom: 0; }
                                            .dark .rich-text-content blockquote { border-color: #374151; color: #9ca3af; }
                                        </style>
                                        @if($selectedProject['description'])
                                            @if(strip_tags($selectedProject['description']) === $selectedProject['description'])
                                                {!! nl2br(e($selectedProject['description'])) !!}
                                            @else
                                                {!! $selectedProject['description'] !!}
                                            @endif
                                        @else
                                            <span class="text-zinc-400 italic">Add a more detailed description...</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Checklist --}}
                    <div class="flex items-start gap-4">
                        <div class="mt-1 p-2 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 shadow-sm">
                            <flux:icon.check-circle class="w-5 h-5" />
                        </div>
                        <div class="flex-1 space-y-5" x-data="{ localHideCompleted: false }">
                            <div class="flex items-center justify-between" id="checklist-section">
                                <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Checklist</h3>
                                <div class="flex gap-2">
                                    <flux:button type="button" @click="localHideCompleted = !localHideCompleted" variant="subtle" size="sm" class="bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 border-none rounded-lg transition-all shadow-sm w-32 justify-center">
                                        <span x-text="localHideCompleted ? 'Show completed' : 'Hide completed'"></span>
                                    </flux:button>
                                </div>
                            </div>
                            
                            @php
                                $progress = $this->getSubtaskProgress();
                            @endphp
                            
                            <div class="flex items-center gap-4 mb-6 relative">
                                <div class="text-[13px] font-bold {{ $progress == 100 ? 'text-emerald-500 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }} w-10 text-right tabular-nums transition-colors duration-500">{{ $progress }}%</div>
                                <div class="flex-1 bg-zinc-100 dark:bg-zinc-800/80 rounded-full h-2 shadow-inner overflow-hidden border border-zinc-200/50 dark:border-zinc-700/50 relative">
                                    <div class="absolute inset-y-0 left-0 {{ $progress == 100 ? 'bg-gradient-to-r from-emerald-400 to-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.3)]' : 'bg-gradient-to-r from-indigo-500 to-indigo-400 shadow-[0_0_10px_rgba(99,102,241,0.3)]' }} rounded-full transition-all duration-700 ease-[cubic-bezier(0.34,1.56,0.64,1)]" style="width: {{ $progress }}%"></div>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                @include('livewire.workspace.partials.subtask-item', ['subtasks' => $selectedProject['subtasks'] ?? [], 'level' => 1, 'isOwner' => $isOwner])
                                
                                <div class="pt-2 pl-2" x-data="{ isAdding: false, title: '' }" @trigger-add-subtask.window="isAdding = true; $nextTick(() => { document.getElementById('checklist-section').scrollIntoView({behavior: 'smooth', block: 'center'}); $refs.rootSubtaskInput.focus(); })">
                                    <div x-show="!isAdding">
                                        <flux:button variant="subtle" size="sm" class="bg-zinc-100/50 hover:bg-zinc-100 dark:bg-zinc-800/30 dark:hover:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border border-transparent hover:border-zinc-200 dark:hover:border-zinc-700 rounded-xl transition-all font-semibold shadow-sm px-4" @click="isAdding = true; $nextTick(() => $refs.rootSubtaskInput.focus())">Add a task</flux:button>
                                    </div>
                                    <div x-show="isAdding" class="flex gap-2 items-center" x-transition>
                                        <input x-ref="rootSubtaskInput" x-model="title" placeholder="Task title..." class="w-full max-w-sm h-9 text-sm rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 outline-none focus:ring-2 focus:ring-indigo-500/20 dark:text-white transition-all shadow-sm" @keydown.enter="$wire.addSubtask(null, title).then(() => { isAdding = false; title = ''; })" />
                                        <flux:button size="sm" variant="primary" class="!h-9 !px-4 shadow-sm transition-transform active:scale-95 !rounded-xl" @click="$wire.addSubtask(null, title).then(() => { isAdding = false; title = ''; })" x-bind:disabled="!title">Add</flux:button>
                                        <flux:button size="sm" variant="ghost" class="!h-9 !px-4 !rounded-xl" @click="isAdding = false">Cancel</flux:button>
                                    </div>
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
                                                <img src="{{ asset('storage/' . $attachment['file_path']) }}" alt="{{ $attachment['file_name'] }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
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
                            <div class="pt-2 relative">
                                <input type="file" wire:model="attachmentFile" id="attachmentFile-{{ $selectedProject['id'] }}" class="hidden" />
                                <label for="attachmentFile-{{ $selectedProject['id'] }}" class="inline-flex items-center justify-center gap-2 whitespace-nowrap text-sm bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 border-none rounded-lg transition-all font-medium shadow-sm h-8 px-3 cursor-pointer">
                                    <flux:icon.paper-clip class="w-4 h-4" />
                                    Add an attachment
                                </label>
                                <div wire:loading wire:target="attachmentFile" class="ml-2 text-xs text-zinc-500">
                                    Uploading...
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    {{-- Watch attachmentFile property --}}
                    <div x-init="$watch('$wire.attachmentFile', value => { if(value) $wire.uploadAttachment() })"></div>

                    {{-- 6. Activity & Comments --}}
                    @include('livewire.workspace.partials.project-activity')
                </div>

                {{-- Right Column: Sidebar Actions --}}
                <div class="shrink-0 transition-all duration-500 ease-out relative md:pl-6 -my-6 py-6 md:py-6 rounded-r-2xl flex flex-col" x-bind:class="sidebarOpen ? 'w-full md:w-[180px] space-y-8 pt-12' : 'w-full md:w-[84px] md:space-y-8 md:pt-12 max-sm:p-0 max-sm:m-0 max-sm:h-0 max-sm:overflow-hidden max-sm:border-none'">
                    {{-- Sidebar Toggle Button --}}
                    <div class="max-sm:fixed max-sm:bottom-6 max-sm:right-6 max-sm:z-[100] md:absolute md:top-4 md:-left-3.5 flex justify-end z-10">
                        <button @click="sidebarOpen = !sidebarOpen" class="flex items-center justify-center p-3 md:p-1.5 text-zinc-500 md:text-zinc-400 bg-white dark:bg-zinc-800 hover:text-zinc-700 dark:hover:text-zinc-200 border border-zinc-200 dark:border-zinc-700 shadow-lg md:shadow hover:shadow-xl md:hover:shadow-md rounded-full transition-all duration-300 hover:scale-110" title="Toggle Sidebar">
                            {{-- Desktop Icons --}}
                            <flux:icon.chevron-right x-show="sidebarOpen" class="w-3.5 h-3.5 hidden md:block"/>
                            <flux:icon.chevron-left x-show="!sidebarOpen" class="w-3.5 h-3.5 hidden md:block" style="display: none;"/>
                            {{-- Mobile Icons --}}
                            <flux:icon.chevron-up x-show="!sidebarOpen" class="w-6 h-6 md:hidden"/>
                            <flux:icon.chevron-down x-show="sidebarOpen" class="w-6 h-6 md:hidden" style="display: none;"/>
                        </button>
                    </div>

                    <div class="transition-opacity duration-300" x-bind:class="!sidebarOpen ? 'max-sm:hidden' : ''">

                    {{-- Add to card --}}
                    <div>
                        <h4 class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 mb-3 truncate uppercase tracking-widest" x-show="sidebarOpen">Add to card</h4>
                        <div class="flex flex-col gap-2.5">
                            @if($isOwner)
                            <flux:dropdown class="w-full">
                                <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="user" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Members</span>
                                </flux:button>
                                <flux:menu class="max-h-64 overflow-y-auto w-64">
                                    <flux:menu.heading>Assign Members</flux:menu.heading>
                                    @if(isset($workspace['users']))
                                        @foreach($workspace['users'] as $user)
                                            <flux:menu.checkbox wire:click="toggleAssignee({{ $user['id'] }})" :checked="collect($selectedProject['assignees'] ?? [])->pluck('id')->contains($user['id'])">
                                                {{ $user['name'] }}
                                            </flux:menu.checkbox>
                                        @endforeach
                                    @endif
                                </flux:menu>
                            </flux:dropdown>

                            <flux:dropdown class="w-full">
                                <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="tag" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Labels</span>
                                </flux:button>
                                <flux:menu class="max-h-80 overflow-y-auto w-64">
                                    <flux:menu.heading>Assign Labels</flux:menu.heading>
                                    @foreach($availableLabels as $label)
                                        <flux:menu.checkbox wire:click="toggleLabel({{ $label['id'] }})" :checked="collect($selectedProject['labels'] ?? [])->pluck('id')->contains($label['id'])">
                                            <div class="flex items-center gap-2">
                                                <div class="w-3 h-3 rounded-full shadow-sm" style="background-color: {{ $label['color'] }}"></div>
                                                {{ $label['name'] }}
                                            </div>
                                        </flux:menu.checkbox>
                                    @endforeach
                                    <flux:menu.separator />
                                    <flux:menu.item @click="$flux.modal('create-label-modal').show()">
                                        <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400 font-medium w-full">
                                            <flux:icon.plus class="w-4 h-4" />
                                            Buat Label Baru
                                        </div>
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                            <div class="w-full">
                                <flux:button @click="$dispatch('trigger-add-subtask')" variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="check-circle" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
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
                                           onclick="this.showPicker()"
                                           @change="$wire.updateProjectField('due_date', $event.target.value)" 
                                           value="{{ $selectedProject['due_date'] ? \Carbon\Carbon::parse($selectedProject['due_date'])->format('Y-m-d') : '' }}" 
                                           class="hide-calendar-icon w-full h-10 pl-10 pr-3 text-sm text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] cursor-pointer outline-none focus:ring-2 focus:ring-indigo-500/20" />
                                    <div class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors">
                                        <flux:icon.calendar-days class="w-5 h-5" />
                                    </div>
                                </div>
                                
                                <div x-show="!sidebarOpen" class="relative overflow-hidden w-10 h-10 rounded-xl">
                                    <flux:button variant="subtle" class="absolute inset-0 w-full justify-center px-0 h-10 w-10 text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 pointer-events-none" icon="calendar-days" title="{{ $selectedProject['due_date'] ? \Carbon\Carbon::parse($selectedProject['due_date'])->format('Y-m-d') : 'Set Due Date' }}"></flux:button>
                                    <input type="date" onclick="this.showPicker()" @change="$wire.updateProjectField('due_date', $event.target.value)" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                                </div>
                            </div>
                            @endif

                            <div class="w-full relative" x-data>
                                <input type="file" wire:model="attachmentFile" x-ref="sidebarFileInput" class="hidden" />
                                <flux:button @click="$refs.sidebarFileInput.click()" variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="paper-clip" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Attachment</span>
                                </flux:button>
                            </div>
                        </div>
                    {{-- Actions --}}
                    <div class="mt-8">
                        <h4 class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 mb-3 truncate uppercase tracking-widest" x-show="sidebarOpen">Actions</h4>
                        <div class="flex flex-col gap-2.5">
                            <div class="w-full">
                                <flux:dropdown class="w-full" position="bottom start">
                                    <flux:button variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98] whitespace-nowrap" icon="arrow-right" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                        <span x-show="sidebarOpen">Move</span>
                                    </flux:button>
                                    <flux:menu class="max-h-64 overflow-y-auto w-64">
                                        <flux:menu.heading>Move to Column</flux:menu.heading>
                                        @if(!empty($columns))
                                            @foreach($columns as $column)
                                                <flux:menu.item wire:click="updateProjectField('workspace_column_id', {{ $column['id'] }})" :disabled="($selectedProject['workspace_column_id'] ?? null) == $column['id']">
                                                    <div class="flex items-center gap-2">
                                                        {{ $column['title'] }}
                                                        @if(($selectedProject['workspace_column_id'] ?? null) == $column['id'])
                                                            <flux:icon.check class="w-4 h-4 ml-auto text-indigo-500" />
                                                        @endif
                                                    </div>
                                                </flux:menu.item>
                                            @endforeach
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                            <div class="w-full">
                                <flux:button wire:click="copyTask" variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="document-duplicate" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Copy</span>
                                </flux:button>
                            </div>
                            @if($isOwner)
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
                            @endif
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
        <div x-data="{ openModal() { $dispatch('task-loaded'); } }">
        </div>
    </flux:modal>

    {{-- Create Label Modal --}}
    <flux:modal name="create-label-modal" class="w-full max-w-sm" x-data="{ newLabelName: '', newLabelColor: '#6366f1' }">
        <div class="space-y-4">
            <div class="border-b border-zinc-200 dark:border-zinc-700 pb-3">
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Buat Label Baru</h3>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1 block">Nama Label</label>
                    <flux:input x-model="newLabelName" placeholder="Contoh: Bug, Feature..." class="w-full" />
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1 block">Warna</label>
                    <input type="color" x-model="newLabelColor" class="w-full h-10 rounded-lg cursor-pointer border border-zinc-200 dark:border-zinc-700" />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button @click="$flux.modal('create-label-modal').close()" variant="ghost">Batal</flux:button>
                <flux:button variant="primary" @click="$wire.createLabel(newLabelName, newLabelColor).then(() => { $flux.modal('create-label-modal').close(); newLabelName = ''; })" x-bind:disabled="!newLabelName">
                    Simpan Label
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <x-rich-editor-modal 
        title="EDITOR DESKRIPSI"
        subtitle="Mode Lengkap"
        wireModel="tempDescription"
        onSave="await $wire.saveRichDescription(); $dispatch('task-updated'); $dispatch('close-rich-editor');"
        onCancel="$dispatch('close-rich-editor')"
        showVariable="showEditor"
    />

    @include('livewire.workspace.partials.project-log-modal')
</div>
