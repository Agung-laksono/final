<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Workspace\Models\TaskSubtask;

// Create a cycle
$t = Modules\Workspace\Models\Task::first();
$s1 = TaskSubtask::create(['task_id' => $t->id, 'title' => 'A', 'parent_id' => null]);
$s2 = TaskSubtask::create(['task_id' => $t->id, 'title' => 'B', 'parent_id' => $s1->id]);
$s1->update(['parent_id' => $s2->id]); // CYCLE!

// Now let's try to eager load
try {
    $subtasks = TaskSubtask::where('task_id', $t->id)
        ->whereNull('parent_id')
        ->with('children.children')
        ->get()
        ->toArray();
    echo "Eager load success. \n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// Clean up
$s1->delete();
$s2->delete();
