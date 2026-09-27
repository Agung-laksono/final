<?php

use App\Models\User;
use App\Events\WorkspaceTaskUpdated;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Models\TaskSubtask;
use Modules\Workspace\Models\TaskComment;
use Modules\Workspace\Models\TaskAttachment;
use Modules\Workspace\Models\TaskUrl;
use Modules\Workspace\Models\TaskActivity;
use Modules\Workspace\Models\TaskLabel;
use Modules\Workspace\Models\KeyResult;
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
    public $referenceItem = null;
    public $attachmentFile = null;
    public $commentAttachmentFile = null;
    public $newUrlLink = '';
    public $newUrlTitle = '';
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
            'keyResults.objective',
            'subtasks' => function($q) {
                $q->with('children.children')->whereNull('parent_id')->orderBy('created_at', 'asc');
            }, 
            'comments.user', 
            'comments.parent.user',
            'attachments', 
            'urls',
            'timeLogs',
            'activities.user'
        ])->find($taskId);

        if ($task) {
            // Catat waktu baca user (Read Receipt)
            $task->userReads()->syncWithoutDetaching([
                auth()->id() => ['last_read_at' => now()]
            ]);

            $this->selectedProject = $task->toArray();
            
            // Sort activities newest first
            if (isset($this->selectedProject['activities'])) {
                usort($this->selectedProject['activities'], function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
            }

            // Deduplicate URLs for display
            if (isset($this->selectedProject['urls'])) {
                $uniqueUrls = [];
                $seenUrls = [];
                foreach ($this->selectedProject['urls'] as $url) {
                    $normalized = rtrim(strtolower(str_replace(['http://', 'https://', 'www.'], '', $url['url'])), '/');
                    if (!in_array($normalized, $seenUrls)) {
                        $seenUrls[] = $normalized;
                        $uniqueUrls[] = $url;
                    }
                }
                $this->selectedProject['urls'] = $uniqueUrls;
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

        // Find Markdown links [text](url)
        $formatted = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s\)<]+)\)/i', '<a href="$2" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">$1</a>', $formatted);

        // Convert bare URLs to links, ignoring those already inside <a> tags or other HTML tags
        $formatted = preg_replace_callback('/<a\b[^>]*>.*?<\/a>|<[^>]+>|\bhttps?:\/\/[^\s<]+/is', function($matches) {
            if (str_starts_with($matches[0], '<')) {
                return $matches[0];
            }
            return '<a href="'.$matches[0].'" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">'.$matches[0].'</a>';
        }, $formatted);

        return nl2br($formatted);
    }

    public function reorderSubtasks($itemId, $newParentId, $orderedIds) {
        if (!$this->selectedProject) return;

        $subtask = TaskSubtask::where('task_id', $this->selectedProject['id'])->find($itemId);
        if (!$subtask) return;

        $newParentId = empty($newParentId) ? null : $newParentId;
        
        // Basic safety: Prevent setting itself as parent
        if ((string)$newParentId === (string)$itemId) return;

        // Prevent moving into its own descendants (Basic circular dependency check) & Validate Max Depth
        if ($newParentId) {
            $allSubtasks = TaskSubtask::where('task_id', $this->selectedProject['id'])->get()->keyBy('id')->toArray();
            $curr = $newParentId;
            $visited = [];
            $parentDepth = 1; // Kedalaman target parent (dimulai dari 1)
            
            for ($i = 0; $i < 100; $i++) {
                if (!$curr) break;
                if ((string)$curr === (string)$itemId || isset($visited[$curr])) {
                    // Revert frontend changes if invalid move (circular)
                    $this->selectedProject['subtasks'] = $this->loadSubtasksArray($this->selectedProject['id']);
                    return;
                }
                $visited[$curr] = true;
                $curr = $allSubtasks[$curr]['parent_id'] ?? null;
                if ($curr) $parentDepth++;
            }
            
            // Hitung kedalaman maksimal dari item yang sedang dipindah
            $itemMaxDepth = $this->calculateSubtaskMaxDepth($itemId, $allSubtasks);
            
            // Jika kedalaman total melebihi 5, batalkan pergeseran!
            if ($parentDepth + $itemMaxDepth > 5) {
                // Revert state ke posisi semula
                $this->selectedProject['subtasks'] = $this->loadSubtasksArray($this->selectedProject['id']);
                // Kirim notifikasi error ke layar
                $this->dispatch('toast', type: 'error', message: 'Maksimal 5 Level! Item yang digeser akan melebihi batas level terdalam.');
                return;
            }
        }

        $subtask->update(['parent_id' => $newParentId]);

        foreach ($orderedIds as $index => $id) {
            TaskSubtask::where('task_id', $this->selectedProject['id'])
                ->where('id', $id)
                ->update(['position' => $index]);
        }

        $this->normalizeSubtaskStates($this->selectedProject['id']);
        $this->recordActivity($this->selectedProject['id'], 'Mengurutkan ulang sub-tugas');
        
        $this->selectedProject['subtasks'] = $this->loadSubtasksArray($this->selectedProject['id']);
    }

    private function calculateSubtaskMaxDepth($itemId, $allSubtasks) {
        $maxDepth = 1;
        foreach ($allSubtasks as $sub) {
            if ($sub['parent_id'] == $itemId) {
                $childDepth = 1 + $this->calculateSubtaskMaxDepth($sub['id'], $allSubtasks);
                if ($childDepth > $maxDepth) {
                    $maxDepth = $childDepth;
                }
            }
        }
        return $maxDepth;
    }

    private function normalizeSubtaskStates($taskId) {
        $allSubtasks = TaskSubtask::where('task_id', $taskId)->get()->keyBy('id')->toArray();
        
        $childMap = [];
        foreach ($allSubtasks as $id => $s) {
            $pid = $s['parent_id'];
            if ($pid) {
                $childMap[$pid][] = $id;
            }
        }
        
        $roots = array_filter($allSubtasks, fn($s) => is_null($s['parent_id']));
        
        $updates = [];
        foreach ($roots as $root) {
            $this->normalizeSubtaskMemory($root['id'], $allSubtasks, $childMap, $updates, []);
        }
        
        foreach ($updates as $id => $isCompleted) {
            TaskSubtask::where('id', $id)->update(['is_completed' => $isCompleted]);
        }
    }

    private function normalizeSubtaskMemory($id, &$allSubtasks, &$childMap, &$updates, $visited = []) {
        if (in_array($id, $visited)) return;
        $visited[] = $id;
        
        $children = $childMap[$id] ?? [];
        if (empty($children)) return; // leaf node
        
        // Bottom-up recursion
        foreach ($children as $cid) {
            $this->normalizeSubtaskMemory($cid, $allSubtasks, $childMap, $updates, $visited);
        }
        
        $allCompleted = true;
        foreach ($children as $cid) {
            $childCompleted = $updates[$cid] ?? $allSubtasks[$cid]['is_completed'];
            if (!$childCompleted) {
                $allCompleted = false;
                break;
            }
        }
        
        if ($allSubtasks[$id]['is_completed'] != $allCompleted) {
            $updates[$id] = $allCompleted;
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
    
    public function copyChecklistFrom($sourceTaskId) {
        if (!$this->selectedProject) return;
        
        $sourceSubtasks = TaskSubtask::where('task_id', $sourceTaskId)->whereNull('parent_id')->orderBy('created_at')->get();
        if ($sourceSubtasks->isEmpty()) return;
        
        $this->duplicateSubtasksRecursively($sourceSubtasks, null, $this->selectedProject['id']);
        
        $this->recordActivity($this->selectedProject['id'], 'Menyalin checklist dari task lain.');
        
        $this->loadTask($this->selectedProject['id']);
        $this->dispatch('kanban-reinit');
    }
    
    public function renameSubtask($subtaskId, $newTitle) {
        if (!$this->selectedProject) return;
        
        $newTitle = trim($newTitle);
        if (empty($newTitle)) return;
        
        $subtask = TaskSubtask::where('task_id', $this->selectedProject['id'])->find($subtaskId);
        if (!$subtask || $subtask->title === $newTitle) return;

        $subtask->update(['title' => $newTitle]);
        $this->recordActivity($this->selectedProject['id'], "Mengubah nama checklist menjadi '{$newTitle}'");
        
        // Lightweight reload: hanya perbarui array subtasks di selectedProject
        // tanpa memanggil normalizeSubtaskStates yang berat/rekursif
        $this->selectedProject['subtasks'] = $this->loadSubtasksArray($this->selectedProject['id']);
    }
    
    private function loadSubtasksArray($taskId) {
        $subtasks = TaskSubtask::where('task_id', $taskId)
            ->whereNull('parent_id')
            ->with('children.children.children.children')
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
        return $subtasks;
    }
    
    public function duplicateSubtask($subtaskId) {
        if (!$this->selectedProject) return;
        
        $sourceSubtask = TaskSubtask::where('task_id', $this->selectedProject['id'])->find($subtaskId);
        if (!$sourceSubtask) return;
        
        $newSubtask = TaskSubtask::create([
            'task_id' => $this->selectedProject['id'],
            'parent_id' => $sourceSubtask->parent_id,
            'title' => $sourceSubtask->title . ' (Copy)',
            'is_completed' => false,
            'requires_input' => $sourceSubtask->requires_input,
            'input_value' => null
        ]);
            
        $children = TaskSubtask::where('parent_id', $sourceSubtask->id)->orderBy('created_at')->get();
        if ($children->isNotEmpty()) {
            $this->duplicateSubtasksRecursively($children, $newSubtask->id, $this->selectedProject['id']);
        }
        
        $this->loadTask($this->selectedProject['id']);
        $this->recordActivity($this->selectedProject['id'], 'Menduplikasi checklist: ' . $sourceSubtask->title);
    }
    
    private function duplicateSubtasksRecursively($subtasks, $newParentId, $targetTaskId) {
        foreach ($subtasks as $sub) {
            $newSub = TaskSubtask::create([
                'task_id' => $targetTaskId,
                'parent_id' => $newParentId,
                'title' => $sub->title,
                'is_completed' => false, // reset status
                'requires_input' => $sub->requires_input,
                'input_value' => null // reset input
            ]);
            
            $children = TaskSubtask::where('parent_id', $sub->id)->orderBy('created_at')->get();
            if ($children->isNotEmpty()) {
                $this->duplicateSubtasksRecursively($children, $newSub->id, $targetTaskId);
            }
        }
    }

    /**
     * Dispatch Livewire event (untuk update komponen lokal)
     * DAN broadcast ke Pusher dengan task_id (untuk sinkronisasi user lain di workspace yang sama).
     * Menyertakan task_id agar client penerima bisa filter: hanya reload modal jika task yang sama sedang dibuka.
     */
    private function broadcastTaskUpdated(string $action = 'task_updated'): void
    {
        if ($this->selectedProject) {
            $taskModel = Task::find($this->selectedProject['id']);
            if ($taskModel) {
                $taskModel->update(['last_significant_update_at' => now()]);
                // Otomatis baca juga untuk pembuat perubahan
                $taskModel->userReads()->syncWithoutDetaching([
                    auth()->id() => ['last_read_at' => now()]
                ]);
            }
        }

        $this->dispatch('task-updated');
        if ($this->workspace && $this->selectedProject) {
            $taskId = $this->selectedProject['id'] ?? null;
            $latestActivity = TaskActivity::where('task_id', $taskId)->latest()->first();
            $message = $latestActivity ? $latestActivity->description : 'Memperbarui tugas';

            $assigneeIds = [];
            if (isset($this->selectedProject['assignees']) && is_array($this->selectedProject['assignees'])) {
                $assigneeIds = collect($this->selectedProject['assignees'])->pluck('id')->toArray();
            }

            WorkspaceTaskUpdated::safeDispatch(
                is_array($this->workspace) ? $this->workspace['id'] : $this->workspace->id,
                $action,
                [
                    'task_id' => $taskId,
                    'user'    => auth()->user()->name,
                    'user_avatar' => auth()->user()->avatar ? \Illuminate\Support\Facades\Storage::url(auth()->user()->avatar) : null,
                    'message' => $message,
                    'assignee_ids' => $assigneeIds
                ]  // rich payload: task_id untuk smart filter di client + info toast
            );
        }
    }

    public function updateProjectField($field, $value) {
        if (!$this->selectedProject) return;
        $task = Task::find($this->selectedProject['id']);
        if ($task) {
            $task->update([$field => $value ?: null]);
            
            if ($field === 'title') {
                $this->recordActivity($task->id, 'Mengubah judul task menjadi: ' . $value);
            } else {
                $this->recordActivity($task->id, "Memperbarui {$field} tugas");
            }
            
            $this->loadTask($task->id);
            $this->dispatch('kanban-reinit');
            $this->broadcastTaskUpdated();
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

            $this->broadcastTaskUpdated();
            $this->showProjectModal = false; 
        }
    }

    public function saveRichDescription() {
        if ($this->selectedProject) {
            $this->updateProjectField('description', $this->tempDescription);
            
            // Extract URLs from description
            $this->extractUrlsFromText($this->tempDescription, $this->selectedProject['id']);
            $this->loadTask($this->selectedProject['id']);
            
            $this->showRichEditorModal = false;
        }
    }

    public function openRichEditor($currentDescription) {
        $this->tempDescription = $currentDescription;
        $this->showRichEditorModal = true;
    }

    public function extractUrlsFromText($text, $taskId) {
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $text)); // Handle HTML if present
        $lines = explode("\n", trim($text));
        
        // Pola URL yang lebih kuat (mendukung http://, https://, IP, localhost, dll)
        $urlPattern = '/(?<!@)\b(?:https?:\/\/[\w\.\-\:]+(?:\/[^\s\]<\)*]*)?|(?:www\.)?(?:[a-zA-Z0-9\-]+\.)+[a-zA-Z]{2,}(?:\/[^\s\]<\)*]*)?)/i';
        
        foreach ($lines as $line) {
            // Check for markdown links
            if (preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/i', $line, $mdMatches, PREG_SET_ORDER)) {
                foreach ($mdMatches as $match) {
                    $title = trim($match[1]);
                    $foundUrl = rtrim($match[2], '.,!?)');
                    $tempUrl = preg_match('/^https?:\/\//i', $foundUrl) ? $foundUrl : 'https://' . $foundUrl;
                    if (!in_array(strtolower(pathinfo(parse_url($tempUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)), ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'rar', 'csv'])) {
                        if (!TaskUrl::where('task_id', $taskId)->where('url', $tempUrl)->exists()) {
                            TaskUrl::create(['task_id' => $taskId, 'user_id' => auth()->id(), 'title' => $title, 'url' => $tempUrl]);
                        }
                    }
                    $line = str_replace($match[0], '', $line);
                }
            }
            
            // Check for bare URLs
            if (preg_match_all($urlPattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                $lastOffset = 0;
                foreach ($matches[0] as $match) {
                    $foundUrl = rtrim($match[0], '.,!?)');
                    $offset = $match[1];
                    
                    // Text before URL since the last URL
                    $textBefore = trim(substr($line, $lastOffset, $offset - $lastOffset));
                    $title = trim(preg_replace('/[:\-\>]+$/', '', $textBefore)) ?: null;
                    
                    $tempUrl = preg_match('/^https?:\/\//i', $foundUrl) ? $foundUrl : 'https://' . $foundUrl;
                    if (!in_array(strtolower(pathinfo(parse_url($tempUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)), ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'rar', 'csv'])) {
                        if (!TaskUrl::where('task_id', $taskId)->where('url', $tempUrl)->exists()) {
                            TaskUrl::create(['task_id' => $taskId, 'user_id' => auth()->id(), 'title' => $title, 'url' => $tempUrl]);
                        }
                    }
                    $lastOffset = $offset + strlen($match[0]);
                }
            }
        }
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
            if ($this->referenceItem) {
                $parsedContent = $this->referenceItem['markdown'] . "\n\n" . $parsedContent;
            }

            TaskComment::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'parent_id' => $this->replyToCommentId,
                'content' => trim($parsedContent)
            ]);
            
            // Extract URLs from comment and add to TaskUrls
            $this->extractUrlsFromText($parsedContent, $task->id);
            
            $commentPreview = \Illuminate\Support\Str::limit(trim(strip_tags($this->newComment)), 60);
            if (!empty($commentPreview)) {
                $this->recordActivity($task->id, 'Menambahkan komentar: "' . $commentPreview . '"');
            } else {
                $this->recordActivity($task->id, 'Menambahkan lampiran pada komentar');
            }
            $this->newComment = '';
            $this->replyToCommentId = null;
            $this->referenceItem = null;
            $this->commentAttachmentFile = null;
            $this->loadTask($task->id);
            $this->broadcastTaskUpdated();
        }
    }

    public function cancelReply() {
        $this->replyToCommentId = null;
    }

    public function setReference($type, $scrollId, $title) {
        $this->referenceItem = [
            'type' => $type,
            'title' => $title,
            'scrollId' => $scrollId,
            'markdown' => "[REF:{$type}:{$scrollId}|" . str_replace('|', '', $title) . "]"
        ];
        $this->dispatch('focus-comment-textarea');
    }

    public function cancelReference() {
        $this->referenceItem = null;
    }

    public function editComment($commentId, $newContent) {
        if (empty(trim($newContent))) return;

        $comment = TaskComment::find($commentId);
        if (!$comment) return;

        // Only the author can edit
        if ($comment->user_id !== auth()->id()) return;

        $comment->update(['content' => trim($newContent)]);
        $this->recordActivity($comment->task_id, 'Mengedit komentar');
        
        // Extract URLs from edited comment
        $this->extractUrlsFromText($newContent, $comment->task_id);
        
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
            $this->broadcastTaskUpdated();
        }
    }
    
    public function addUrl() {
        if (!$this->selectedProject || empty(trim($this->newUrlLink))) return;
        
        $task = Task::find($this->selectedProject['id']);
        if ($task) {
            $inputUrl = trim($this->newUrlLink);
            $finalUrl = preg_match('/^https?:\/\//i', $inputUrl) ? $inputUrl : 'https://' . $inputUrl;
            
            TaskUrl::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'title' => trim($this->newUrlTitle) ?: null,
                'url' => $finalUrl
            ]);
            
            $this->recordActivity($task->id, "Menambahkan tautan eksternal: " . ($this->newUrlTitle ?: $finalUrl));
            
            $this->newUrlLink = '';
            $this->newUrlTitle = '';
            $this->loadTask($task->id);
            $this->broadcastTaskUpdated();
        }
    }

    public function editUrlTitle($urlId, $newTitle) {
        if (!$this->selectedProject) return;
        
        $url = TaskUrl::where('task_id', $this->selectedProject['id'])->find($urlId);
        if ($url) {
            $url->update(['title' => trim($newTitle) ?: null]);
            $this->recordActivity($this->selectedProject['id'], "Mengubah judul tautan eksternal menjadi: " . ($newTitle ?: $url->url));
            $this->loadTask($this->selectedProject['id']);
            $this->broadcastTaskUpdated();
        }
    }
    
    public function deleteUrl($urlId) {
        if (!$this->selectedProject) return;
        
        $url = TaskUrl::where('task_id', $this->selectedProject['id'])->find($urlId);
        if ($url) {
            $url->delete();
            $this->recordActivity($this->selectedProject['id'], "Menghapus tautan eksternal.");
            $this->loadTask($this->selectedProject['id']);
            $this->broadcastTaskUpdated();
        }
    }

    public function toggleAssignee($userId) {
        if (!$this->selectedProject) return;
        $task = Task::find($this->selectedProject['id']);
        if ($task) {
            $changes = $task->assignees()->toggle($userId);
            $user = User::find($userId);
            $action = count($changes['attached']) > 0 ? 'Menugaskan' : 'Melepas penugasan';
            $this->recordActivity($task->id, "{$action} {$user->name}");
            $this->loadTask($task->id);
            $this->broadcastTaskUpdated();
        }
    }

    public function toggleLabel($labelId) {
        if (!$this->selectedProject) return;
        $task = Task::find($this->selectedProject['id']);
        if ($task) {
            $task->labels()->toggle($labelId);
            $this->loadTask($task->id);
            $this->broadcastTaskUpdated();
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
            $this->broadcastTaskUpdated();
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
            $this->broadcastTaskUpdated();
        }
    }

    public function deleteSubtask($subtaskId) {
        $subtask = TaskSubtask::find($subtaskId);
        if ($subtask) {
            $taskId = $subtask->task_id;
            $this->recordActivity($taskId, "Menghapus subtask: {$subtask->title}");
            $subtask->delete();
            $this->loadTask($taskId);
            $this->broadcastTaskUpdated();
        }
    }

    public function deleteChecklist() {
        if (!$this->selectedProject) return;
        $taskId = $this->selectedProject['id'];
        
        // Delete all subtasks for this task
        TaskSubtask::where('task_id', $taskId)->delete();
        
        $this->recordActivity($taskId, "Menghapus seluruh checklist");
        $this->loadTask($taskId);
        $this->broadcastTaskUpdated('checklist_deleted');
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
            $this->broadcastTaskUpdated();
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
                $this->broadcastTaskUpdated();
            }
        }
        
        $this->availableLabels = TaskLabel::all()->toArray();
    }

    public function deleteLabel($id) {
        $label = TaskLabel::find($id);
        if ($label) {
            // Unlink from all tasks first just to be safe
            $label->tasks()->detach();
            $label->delete();
            
            $this->availableLabels = TaskLabel::all()->toArray();
            
            if ($this->selectedProject) {
                $task = Task::with('labels')->find($this->selectedProject['id']);
                if ($task) {
                    $this->selectedProject['labels'] = $task->labels->toArray();
                }
            }
        }
    }

    public function linkKeyResult($keyResultId) {
        if (!$this->selectedProject) return;
        $task = Task::find($this->selectedProject['id']);
        if (!$task) return;
        // Attach if not already linked
        if (!$task->keyResults()->where('key_result_id', $keyResultId)->exists()) {
            $task->keyResults()->attach($keyResultId, ['contribution' => 1]);
            $this->recordActivity($task->id, 'Menghubungkan tugas ke Key Result OKR');
            $this->loadTask($task->id);
            $this->broadcastTaskUpdated('task_updated');
        }
    }

    public function unlinkKeyResult($keyResultId) {
        if (!$this->selectedProject) return;
        $task = Task::find($this->selectedProject['id']);
        if (!$task) return;
        $task->keyResults()->detach($keyResultId);
        $this->recordActivity($task->id, 'Melepas hubungan Task dari Key Result OKR');
        $this->loadTask($task->id);
        $this->broadcastTaskUpdated('task_updated');
    }

    /**
     * Dipanggil oleh listener Alpine.js (Echo) saat ada event WorkspaceTaskUpdated.
     * Hanya reload task jika modal sedang terbuka DAN task yang ditampilkan sesuai.
     * Ini memastikan User B yang sedang membuka modal task yang sama langsung melihat update.
     */
    #[\Livewire\Attributes\On('workspace-task-changed')]
    public function realtimeRefresh($taskId = null): void {
        if (!$this->showProjectModal || !$this->selectedProject) return;

        // Jika ada task_id spesifik di payload dan berbeda → skip (update task lain)
        if ($taskId && $this->selectedProject['id'] != $taskId) return;

        $this->loadTask($this->selectedProject['id']);
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
                    $isOwner = false;
                    $isAdminOrLeader = false;
                    $isAssignee = false;

                    if ($workspace) {
                        $workspaceOwnerId = is_array($workspace) ? ($workspace['owner_id'] ?? null) : $workspace->owner_id;
                        $isOwner = $workspaceOwnerId == auth()->id();
                        
                        $users = is_array($workspace) ? ($workspace['users'] ?? []) : (isset($workspace->users) ? $workspace->users : []);
                        $userRole = collect($users)->firstWhere('id', auth()->id())['pivot']['role'] ?? 'member';
                        $isAdminOrLeader = in_array($userRole, ['admin', 'leader']) || $isOwner;
                    }

                    if ($selectedProject) {
                        $isAssignee = collect($selectedProject['assignees'] ?? [])->contains('id', auth()->id());
                    }

                    $canEditTask = $isAdminOrLeader || $isAssignee;
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
                            <div class="flex-1" 
                                wire:key="task-title-{{ $selectedProject['id'] ?? 'new' }}"
                                data-title="{{ $selectedProject['title'] ?? '' }}"
                                x-data="{ 
                                    editingTitle: false, 
                                    localTitle: '',
                                    isSaving: false,
                                    startEdit() {
                                        this.localTitle = this.$root.dataset.title;
                                        this.editingTitle = true;
                                        this.$nextTick(() => this.$refs.titleInput.focus());
                                    },
                                    save() {
                                        if (this.isSaving) return;
                                        if (this.localTitle !== this.$root.dataset.title && this.localTitle.trim() !== '') {
                                            this.isSaving = true;
                                            this.$wire.updateProjectField('title', this.localTitle).then(() => {
                                                this.isSaving = false;
                                                this.editingTitle = false;
                                            });
                                        } else {
                                            this.editingTitle = false;
                                        }
                                    }
                                }">
                                <div x-show="!editingTitle" @if($canEditTask) @click="startEdit()" class="cursor-pointer group/title rounded-lg -ml-2 p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors flex items-center gap-3" @else class="group/title rounded-lg -ml-2 p-2 flex items-center gap-3" @endif>
                                    <h2 class="text-2xl font-extrabold text-zinc-900 dark:text-white tracking-tight leading-tight">
                                        <span x-show="isSaving" x-text="localTitle" x-cloak></span>
                                        <span x-show="!isSaving">{{ $selectedProject['title'] }}</span>
                                    </h2>
                                    <div x-show="isSaving" class="text-zinc-400" x-cloak>
                                        <flux:icon.arrow-path class="w-5 h-5 animate-spin" />
                                    </div>
                                </div>
                                <div x-show="editingTitle" class="-ml-2 p-1" @click.away="save()" x-cloak>
                                    <input type="text" x-ref="titleInput" x-model="localTitle" @keydown.enter="save()" @keydown.escape="editingTitle = false" class="w-full text-2xl font-extrabold text-zinc-900 dark:text-white tracking-tight leading-tight bg-white dark:bg-zinc-800 border-2 border-indigo-400 dark:border-indigo-500 rounded-lg px-2 py-1 shadow-sm focus:outline-none focus:ring-4 focus:ring-indigo-500/20 transition-all" />
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
                                                <div class="flex items-center justify-between w-full group/label">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-3 h-3 rounded-full shadow-sm" style="background-color: {{ $label['color'] }}"></div>
                                                        <span>{{ $label['name'] }}</span>
                                                    </div>
                                                    <button wire:click.stop="deleteLabel({{ $label['id'] }})" wire:confirm="Hapus label ini secara permanen?" class="opacity-0 group-hover/label:opacity-100 text-red-400 hover:text-red-600 transition-all px-1" title="Hapus Label">
                                                        <flux:icon.trash class="w-3.5 h-3.5" />
                                                    </button>
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
                                    <button class="flex items-center gap-2 bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 px-3 py-1.5 rounded-lg hover:bg-zinc-200/80 dark:hover:bg-zinc-700 hover:shadow-sm transition-all duration-300 @if($isOwner) cursor-pointer group @endif shadow-sm" @if(!$isOwner) disabled @endif>
                                        <div class="w-3 h-3 rounded-full shadow-inner border border-white/20" style="background-color: {{ $pConfig['color'] }}"></div>
                                        <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $pConfig['label'] }}</span>
                                        @if($isOwner)
                                        <flux:icon.chevron-down class="w-3 h-3 text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors ml-1" />
                                        @endif
                                    </button>
                                    
                                    @if($isOwner)
                                    <flux:menu>
                                        <flux:menu.heading>Ubah Prioritas</flux:menu.heading>
                                        <flux:menu.item wire:click="updateProjectField('priority', 'critical')">
                                            <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-red-500"></div> Critical</div>
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="updateProjectField('priority', 'high')">
                                            <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div> High</div>
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="updateProjectField('priority', 'normal')">
                                            <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-slate-500"></div> Normal</div>
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="updateProjectField('priority', 'low')">
                                            <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-slate-400"></div> Low</div>
                                        </flux:menu.item>
                                    </flux:menu>
                                    @endif
                                </flux:dropdown>
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

                    {{-- Key Results (OKR) --}}
                    @php
                        $linkedKrs = $selectedProject['key_results'] ?? [];
                        $allKrs = KeyResult::whereHas('objective', fn($q) => $q->where('workspace_id', $workspace['id']))->with('objective')->get();
                    @endphp
                    @if($allKrs->isNotEmpty() || count($linkedKrs) > 0)
                    <div class="ml-14">
                        <h3 class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest mb-3">🎯 Key Results (OKR)</h3>
                        <div class="flex flex-wrap gap-2">
                            {{-- Already linked --}}
                            @foreach($linkedKrs as $kr)
                            <div class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg text-xs font-medium text-indigo-700 dark:text-indigo-300 group">
                                <flux:icon.flag class="w-3 h-3 shrink-0" />
                                <span class="break-words font-medium" title="{{ $kr['title'] }}">{{ $kr['title'] }}</span>
                                @if($isOwner)
                                <button wire:click="unlinkKeyResult({{ $kr['id'] }})"
                                        class="opacity-0 group-hover:opacity-100 ml-1 text-indigo-400 hover:text-red-500 transition-all"
                                        title="Lepas hubungan">
                                    <flux:icon.x-mark class="w-3 h-3" />
                                </button>
                                @endif
                            </div>
                            @endforeach

                            {{-- Dropdown to add more --}}
                            @if($isOwner && $allKrs->isNotEmpty())
                            <flux:dropdown>
                                <button class="h-8 px-2.5 rounded-lg bg-zinc-100/80 hover:bg-indigo-50 dark:bg-zinc-800/80 dark:hover:bg-indigo-900/20 border-2 border-dashed border-zinc-300 dark:border-zinc-600 hover:border-indigo-400 dark:hover:border-indigo-600 flex items-center gap-1.5 text-xs text-zinc-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all">
                                    <flux:icon.plus class="w-3.5 h-3.5" />
                                    Kaitkan KR
                                </button>
                                <flux:menu class="max-h-64 overflow-y-auto w-72">
                                    <flux:menu.heading>Pilih Key Result</flux:menu.heading>
                                    @foreach($allKrs as $kr)
                                    @php
                                        $alreadyLinked = collect($linkedKrs)->pluck('id')->contains($kr->id);
                                    @endphp
                                    <flux:menu.item wire:click="{{ $alreadyLinked ? '' : 'linkKeyResult(' . $kr->id . ')' }}"
                                                   class="{{ $alreadyLinked ? 'opacity-50 cursor-default' : '' }}">
                                        <div class="flex items-start gap-2 w-full">
                                            <flux:icon.flag class="w-3.5 h-3.5 text-indigo-500 mt-0.5 shrink-0" />
                                            <div>
                                                <div class="text-sm font-medium leading-tight">{{ $kr->title }}</div>
                                                <div class="text-[10px] text-zinc-400 mt-0.5">{{ $kr->objective->title ?? '' }}</div>
                                            </div>
                                            @if($alreadyLinked)
                                            <flux:icon.check class="w-3.5 h-3.5 text-green-500 ml-auto shrink-0" />
                                            @endif
                                        </div>
                                    </flux:menu.item>
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                            @endif
                        </div>
                    </div>
                    @endif

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
                        <div class="sticky top-2 z-20 mt-1 p-2 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 shadow-sm">
                            <flux:icon.check-circle class="w-5 h-5" />
                        </div>
                        <div class="flex-1 space-y-5" x-data="{ localHideCompleted: false }">
                            <div class="sticky top-0 z-20 bg-white dark:bg-zinc-900 pt-1 pb-4 -mt-1 border-b border-transparent shadow-[0_10px_20px_-15px_rgba(0,0,0,0.1)] dark:shadow-none">
                                <div class="flex items-center justify-between mb-4" id="checklist-section">
                                    <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Checklist</h3>
                                    <div class="flex gap-2">
                                        @php
                                            $tasksWithChecklists = collect();
                                            if ($selectedProject) {
                                                $tasksWithChecklists = \Modules\Workspace\Models\Task::where('workspace_id', $workspace['id'])
                                                    ->where('id', '!=', $selectedProject['id'])
                                                    ->whereHas('subtasks')
                                                    ->orderBy('title')
                                                    ->get();
                                            }
                                        @endphp
                                        @if(($isOwner ?? false) && $tasksWithChecklists->isNotEmpty())
                                        <flux:dropdown>
                                            <flux:button variant="subtle" size="sm" class="bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 border-none rounded-lg transition-all shadow-sm">
                                                Copy dari...
                                            </flux:button>
                                            <flux:menu class="max-h-64 overflow-y-auto w-64">
                                                <flux:menu.heading>Pilih task sumber</flux:menu.heading>
                                                @foreach($tasksWithChecklists as $t)
                                                    <flux:menu.item wire:click="copyChecklistFrom({{ $t->id }})" icon="document-duplicate">
                                                        {{ Str::limit($t->title, 25) }}
                                                    </flux:menu.item>
                                                @endforeach
                                            </flux:menu>
                                        </flux:dropdown>
                                        @endif
                                        
                                        <flux:button type="button" @click="localHideCompleted = !localHideCompleted" variant="subtle" size="sm" class="bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 border-none rounded-lg transition-all shadow-sm w-32 justify-center">
                                            <span x-text="localHideCompleted ? 'Show completed' : 'Hide completed'"></span>
                                        </flux:button>
                                    </div>
                                </div>
                                
                                @php
                                    $progress = $this->getSubtaskProgress();
                                @endphp
                                
                                <div class="flex items-center gap-4 relative">
                                    <div class="text-[13px] font-bold {{ $progress == 100 ? 'text-emerald-500 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }} w-10 text-right tabular-nums transition-colors duration-500">{{ $progress }}%</div>
                                    <div class="flex-1 bg-zinc-100 dark:bg-zinc-800/80 rounded-full h-2 shadow-inner overflow-hidden border border-zinc-200/50 dark:border-zinc-700/50 relative">
                                        <div class="absolute inset-y-0 left-0 {{ $progress == 100 ? 'bg-gradient-to-r from-emerald-400 to-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.3)]' : 'bg-gradient-to-r from-indigo-500 to-indigo-400 shadow-[0_0_10px_rgba(99,102,241,0.3)]' }} rounded-full transition-all duration-700 ease-[cubic-bezier(0.34,1.56,0.64,1)]" style="width: {{ $progress }}%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-3"
                                 id="subtask-root-container"
                                 data-parent-id=""
                                 x-data="{
                                    init() {
                                        if (window.Sortable && !this.$el._sortable_initialized) {
                                            this.$el._sortable_initialized = true;
                                            window.Sortable.create(this.$el, {
                                                group: 'subtasks',
                                                animation: 250,
                                                fallbackOnBody: true,
                                                swapThreshold: 0.65,
                                                handle: '.subtask-drag-handle',
                                                ghostClass: 'subtask-ghost',
                                                dragClass: 'subtask-drag',
                                                chosenClass: 'subtask-chosen',
                                                onEnd: (e) => {
                                                    const itemId = e.item.getAttribute('data-subtask-id');
                                                    const newParentId = e.to.getAttribute('data-parent-id');
                                                    const orderedIds = Array.from(e.to.children)
                                                        .filter(c => c.hasAttribute('data-subtask-id'))
                                                        .map(c => c.getAttribute('data-subtask-id'));
                                                    
                                                    // Deteksi apakah onEnd dipicu berkali-kali secara bersamaan (Request Flood)
                                                    const now = Date.now();
                                                    if (window._lastSubtaskDropTime && (now - window._lastSubtaskDropTime < 100)) {
                                                        alert('⚠ ERROR TERDETEKSI: SortableJS memicu onEnd berkali-kali dalam waktu bersamaan! Ini yang membuat server macet.');
                                                        return; // Hentikan agar tidak membombardir server
                                                    }
                                                    window._lastSubtaskDropTime = now;

                                                    // Revert DOM block removed for smooth UI
                                                    
                                                    window.dispatchEvent(new CustomEvent('debug-log', {
                                                        detail: {msg: 'Root Drop: Item ' + itemId + ' to Parent ' + (newParentId || 'null') + ' | New Order: [' + orderedIds.join(', ') + ']'}
                                                    }));

                                                    if (itemId) {
                                                        $wire.reorderSubtasks(itemId, newParentId || null, orderedIds);
                                                    }
                                                }
                                            });
                                        }
                                    }
                                 }"
                            >
                                @include('livewire.workspace.partials.subtask-item', ['subtasks' => $selectedProject['subtasks'] ?? [], 'level' => 1, 'isOwner' => $isOwner])
                                
                                <div class="pt-2 pl-2" x-data="{ isAdding: false, title: '', isSaving: false }" @trigger-add-subtask.window="isAdding = true; $nextTick(() => { document.getElementById('checklist-section').scrollIntoView({behavior: 'smooth', block: 'center'}); $refs.rootSubtaskInput.focus(); })">
                                    <div x-show="!isAdding">
                                        <flux:button variant="subtle" size="sm" class="bg-zinc-100/50 hover:bg-zinc-100 dark:bg-zinc-800/30 dark:hover:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border border-transparent hover:border-zinc-200 dark:hover:border-zinc-700 rounded-xl transition-all font-semibold shadow-sm px-4" @click="isAdding = true; $nextTick(() => $refs.rootSubtaskInput.focus())">Add a task</flux:button>
                                    </div>
                                    <div x-show="isAdding" class="flex gap-2 items-center" x-transition>
                                        <input x-ref="rootSubtaskInput" x-model="title" x-bind:disabled="isSaving" placeholder="Task title..." class="w-full max-w-sm h-9 text-sm rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 outline-none focus:ring-2 focus:ring-indigo-500/20 dark:text-white transition-all shadow-sm disabled:opacity-50" @keydown.enter="if(!title || isSaving) return; isSaving = true; $wire.addSubtask(null, title).then(() => { isAdding = false; title = ''; isSaving = false; })" />
                                        <flux:button size="sm" variant="primary" class="!h-9 !px-4 shadow-sm transition-transform active:scale-95 !rounded-xl" @click="isSaving = true; $wire.addSubtask(null, title).then(() => { isAdding = false; title = ''; isSaving = false; })" x-bind:disabled="!title || isSaving">
                                            <span x-text="isSaving ? 'Saving...' : 'Add'"></span>
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" class="!h-9 !px-4 !rounded-xl" @click="isAdding = false" x-bind:disabled="isSaving">Cancel</flux:button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 5. Attachments --}}
                    <div class="flex items-start gap-4">
                        <div class="mt-1 p-2 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 shadow-sm">
                            <flux:icon.paper-clip class="w-5 h-5" />
                        </div>
                        <div class="flex-1 space-y-4">
                            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Attachments</h3>
                            @if(!empty($selectedProject['attachments']))
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                @foreach($selectedProject['attachments'] as $attachment)
                                    <div id="attachment-{{ $loop->index }}" class="group flex flex-col bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 hover:border-indigo-300 dark:hover:border-indigo-500/50 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer">
                                        <div class="h-24 bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center relative overflow-hidden group/img">
                                            <div class="absolute inset-0 bg-gradient-to-b from-transparent to-black/20 opacity-0 group-hover/img:opacity-100 transition-opacity z-10"></div>
                                            <div class="absolute top-2 right-2 opacity-0 group-hover/img:opacity-100 transition-opacity z-20">
                                                <flux:button variant="filled" size="xs" wire:click="setReference('lampiran', 'attachment-{{ $loop->index }}', '{{ addslashes($attachment['file_name']) }}')" class="bg-black/50 hover:bg-black/70 text-white !h-6 !px-2 text-[10px] !rounded-md backdrop-blur-sm border border-white/20">Quote</flux:button>
                                            </div>
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
                            @endif
                            <div class="pt-2 relative flex items-center gap-2">
                                <input type="file" wire:model="attachmentFile" id="attachmentFile-{{ $selectedProject['id'] }}" class="hidden" />
                                <label for="attachmentFile-{{ $selectedProject['id'] }}" class="inline-flex items-center justify-center gap-2 whitespace-nowrap text-sm bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 border-none rounded-lg transition-all font-medium shadow-sm h-8 px-3 cursor-pointer">
                                    <flux:icon.paper-clip class="w-4 h-4" />
                                    Add an attachment
                                </label>
                                
                                <div x-data="{ addingLink: false }" class="relative">
                                    <flux:button size="sm" variant="subtle" @click="addingLink = !addingLink" class="bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 border-none shadow-sm font-medium h-8 px-3 rounded-lg flex items-center gap-2">
                                        <flux:icon.link class="w-4 h-4" />
                                        Add a link
                                    </flux:button>
                                    
                                    <div x-show="addingLink" class="absolute left-0 z-50 mt-2 p-3 bg-white dark:bg-zinc-800 rounded-xl shadow-lg border border-zinc-200 dark:border-zinc-700 flex flex-col gap-2 w-72" @click.away="addingLink = false" x-cloak>
                                        <input type="url" wire:model="newUrlLink" placeholder="https://..." class="w-full h-8 text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 outline-none focus:ring-2 focus:ring-indigo-500/20 dark:text-white shadow-sm" />
                                        <input type="text" wire:model="newUrlTitle" placeholder="Judul (Opsional)" class="w-full h-8 text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 outline-none focus:ring-2 focus:ring-indigo-500/20 dark:text-white shadow-sm" @keydown.enter="$wire.addUrl().then(() => addingLink = false)" />
                                        <flux:button size="sm" variant="primary" @click="$wire.addUrl().then(() => addingLink = false)" class="mt-1 shadow-sm transition-transform active:scale-95">Simpan Tautan</flux:button>
                                    </div>
                                </div>
                                
                                <div wire:loading wire:target="attachmentFile" class="ml-2 text-xs text-zinc-500">
                                    Uploading...
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Watch attachmentFile property --}}
                    <div x-init="$watch('$wire.attachmentFile', value => { if(value) $wire.uploadAttachment() })"></div>

                    {{-- 6. URLs --}}
                    @if(!empty($selectedProject['urls']))
                    <div class="flex items-start gap-4">
                        <div class="mt-1 p-2 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 shadow-sm">
                            <flux:icon.link class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0 space-y-4">
                            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Tautan Eksternal</h3>
                            <div class="space-y-2">
                                @foreach($selectedProject['urls'] as $url)
                                    <div id="url-{{ $url['id'] }}" x-data="{ editingUrl: false, editTitle: '{{ addslashes($url['title'] ?: $url['url']) }}' }" class="group flex items-center justify-between p-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 hover:border-indigo-300 dark:hover:border-indigo-500/50 rounded-xl shadow-sm transition-all duration-300 gap-2">
                                        <div class="flex-1 flex items-center gap-3 min-w-0 overflow-hidden">
                                            <div class="p-2 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-500 dark:text-indigo-400 rounded-lg shrink-0">
                                                <img src="https://www.google.com/s2/favicons?domain={{ parse_url($url['url'], PHP_URL_HOST) }}&sz=64" class="w-5 h-5 rounded-sm" alt="Favicon">
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div x-show="!editingUrl">
                                                    <a href="{{ $url['url'] }}" target="_blank" class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 hover:text-indigo-600 dark:hover:text-indigo-400 truncate block">
                                                        {{ $url['title'] ?: $url['url'] }}
                                                    </a>
                                                    <span class="text-[11px] text-zinc-400 dark:text-zinc-500 truncate block">Ditambahkan pada {{ \Carbon\Carbon::parse($url['created_at'])->format('d M, H:i') }}</span>
                                                </div>
                                                <div x-show="editingUrl" style="display: none;" class="flex items-center gap-2">
                                                    <input type="text" x-model="editTitle" @keydown.enter="$wire.editUrlTitle({{ $url['id'] }}, editTitle).then(() => editingUrl = false)" @keydown.escape="editingUrl = false" class="flex-1 text-sm rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2 py-1 outline-none focus:ring-1 focus:ring-indigo-500 dark:text-white" autofocus>
                                                    <button @click="$wire.editUrlTitle({{ $url['id'] }}, editTitle).then(() => editingUrl = false)" class="text-indigo-500 hover:text-indigo-600" title="Simpan"><flux:icon.check class="w-4 h-4" /></button>
                                                    <button @click="editingUrl = false" class="text-zinc-400 hover:text-zinc-500" title="Batal"><flux:icon.x-mark class="w-4 h-4" /></button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 shrink-0" x-show="!editingUrl">
                                            <flux:button variant="ghost" size="xs" wire:click="setReference('tautan', 'url-{{ $url['id'] }}', '{{ addslashes($url['title'] ?: $url['url']) }}')" class="text-zinc-500 hover:text-indigo-600 dark:hover:text-indigo-400 h-7 px-2 rounded-lg">Quote</flux:button>
                                            @if($isOwner ?? false || $url['user_id'] == auth()->id())
                                                <flux:button variant="ghost" size="xs" @click="editingUrl = true" class="text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 h-7 px-2 rounded-lg">Edit</flux:button>
                                                <flux:button variant="ghost" size="xs" wire:click="deleteUrl({{ $url['id'] }})" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 h-7 px-2 rounded-lg">Hapus</flux:button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

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
                                            <div class="flex items-center justify-between w-full group/label2">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-3 h-3 rounded-full shadow-sm" style="background-color: {{ $label['color'] }}"></div>
                                                    <span>{{ $label['name'] }}</span>
                                                </div>
                                                <button wire:click.stop="deleteLabel({{ $label['id'] }})" wire:confirm="Hapus label ini secara permanen?" class="opacity-0 group-hover/label2:opacity-100 text-red-400 hover:text-red-600 transition-all px-1" title="Hapus Label">
                                                    <flux:icon.trash class="w-3.5 h-3.5" />
                                                </button>
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
                            <div class="w-full relative" x-data="{ addingLink: false }">
                                <flux:button @click="addingLink = !addingLink" variant="subtle" class="w-full justify-start text-zinc-600 dark:text-zinc-300 bg-zinc-100/80 dark:bg-zinc-800/50 hover:bg-white dark:hover:bg-zinc-700/80 hover:shadow-sm border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 font-semibold rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]" icon="link" x-bind:class="!sidebarOpen ? 'px-0 justify-center h-10 w-10' : ''">
                                    <span x-show="sidebarOpen">Link</span>
                                </flux:button>
                                <div x-show="addingLink" class="absolute right-full mr-2 top-0 z-50 p-3 bg-white dark:bg-zinc-800 rounded-xl shadow-lg border border-zinc-200 dark:border-zinc-700 flex flex-col gap-2 w-72" @click.away="addingLink = false" x-cloak>
                                    <input type="url" wire:model="newUrlLink" placeholder="https://..." class="w-full h-8 text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 outline-none focus:ring-2 focus:ring-indigo-500/20 dark:text-white shadow-sm" />
                                    <input type="text" wire:model="newUrlTitle" placeholder="Judul (Opsional)" class="w-full h-8 text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 outline-none focus:ring-2 focus:ring-indigo-500/20 dark:text-white shadow-sm" @keydown.enter="$wire.addUrl().then(() => addingLink = false)" />
                                    <flux:button size="sm" variant="primary" @click="$wire.addUrl().then(() => addingLink = false)" class="mt-1 shadow-sm transition-transform active:scale-95">Simpan Tautan</flux:button>
                                </div>
                            </div>
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
    
    <style>
    /* SortableJS UX Enhancements */
    .subtask-ghost {
        opacity: 0.5;
        background-color: #f8fafc !important; /* zinc-50 */
        border: 2px dashed #818cf8 !important; /* indigo-400 */
        border-radius: 0.5rem;
    }
    .dark .subtask-ghost {
        background-color: rgba(24, 24, 27, 0.5) !important; /* zinc-900 */
        border-color: #6366f1 !important; /* indigo-500 */
    }
    .subtask-drag {
        opacity: 1 !important;
        background-color: #ffffff !important;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        transform: rotate(2deg) scale(1.02);
        border-radius: 0.5rem;
        cursor: grabbing !important;
        z-index: 9999 !important;
    }
    .dark .subtask-drag {
        background-color: #27272a !important; /* zinc-800 */
    }
    .subtask-chosen {
        background-color: #f1f5f9; /* slate-100 */
        cursor: grabbing !important;
    }
    .dark .subtask-chosen {
        background-color: rgba(63, 63, 70, 0.4); /* zinc-700 */
    }
    </style>
</div>
