<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Workspace\Models\TaskSubtask;

// Find a task with subtasks
$taskId = TaskSubtask::first()->task_id ?? null;
if (!$taskId) {
    echo "No tasks with subtasks found.\n";
    exit;
}
$subtasks = TaskSubtask::where('task_id', $taskId)->get();
echo "Task ID: $taskId\n";
echo "Subtasks count: " . $subtasks->count() . "\n";

// Emulate normalizeSubtaskStates
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
function normalizeSubtaskMemory($id, &$allSubtasks, &$childMap, &$updates, $visited = []) {
    if (in_array($id, $visited)) {
        echo "CYCLE DETECTED at $id\n";
        return;
    }
    $visited[] = $id;
    $children = $childMap[$id] ?? [];
    if (empty($children)) return;
    foreach ($children as $cid) {
        normalizeSubtaskMemory($cid, $allSubtasks, $childMap, $updates, $visited);
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

foreach ($roots as $root) {
    normalizeSubtaskMemory($root['id'], $allSubtasks, $childMap, $updates, []);
}
echo "Updates: " . count($updates) . "\n";

// Emulate loadSubtasksArray
$subtasksArray = TaskSubtask::where('task_id', $taskId)
    ->whereNull('parent_id')
    ->with('children.children')
    ->orderBy('position', 'asc')
    ->orderBy('created_at', 'asc')
    ->get()
    ->toArray();
    
echo "Array generated successfully. First root ID: " . ($subtasksArray[0]['id'] ?? 'none') . "\n";
echo "Done.\n";
