<?php

$file = __DIR__ . '/resources/views/livewire/workspace/board-kanban.blade.php';
$content = file_get_contents($file);

$replacements = [
    'Modules\Marketing' => 'Modules\Workspace\app',
    'MarketingProject' => 'Task',
    'MarketingSubtask' => 'TaskSubtask',
    'MarketingComment' => 'TaskComment',
    'MarketingAttachment' => 'TaskAttachment',
    'MarketingActivity' => 'TaskActivity',
    'MarketingTimeLog' => 'TaskTimeLog',
    'MarketingLabel' => 'TaskLabel',
    'marketing_project_id' => 'task_id',
    'marketing_attachments' => 'task_attachments',
    'Marketing Project Board' => 'Workspace Board'
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

// Ensure the Volt component accepts the workspace
$content = str_replace(
    'public array $projects = [];',
    "public \$workspace;\n    public array $projects = [];",
    $content
);

file_put_contents($file, $content);
echo "Blade fixed\n";
