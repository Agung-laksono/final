<?php

$file = __DIR__ . '/resources/views/livewire/workspace/board-kanban.blade.php';
$content = file_get_contents($file);

// Replace hardcoded columns array with empty array
$content = preg_replace('/public array \$columns = \[.*?\];/s', 'public array $columns = [];', $content);

// Update mount
$content = str_replace(
    "public function mount() {\n        \$this->loadProjects();\n    }",
    "public function mount(\Modules\Workspace\app\Models\Workspace \$workspace) {\n        \$this->workspace = \$workspace;\n        \$this->columns = \$workspace->columns()->orderBy('position')->get()->toArray();\n        \$this->loadProjects();\n    }",
    $content
);

// Update loadProjects
$content = str_replace(
    "Task::with([",
    "\$this->workspace->tasks()->with([",
    $content
);

// Update addingTaskToColumn which is now a column ID
$content = str_replace(
    "where('status', \$this->addingTaskToColumn)",
    "where('workspace_column_id', \$this->addingTaskToColumn)",
    $content
);

$content = str_replace(
    "'status' => \$this->addingTaskToColumn,",
    "'workspace_column_id' => \$this->addingTaskToColumn,",
    $content
);

$content = str_replace(
    "['title' => trim(\$this->newTaskTitle),",
    "['title' => trim(\$this->newTaskTitle),\n            'workspace_id' => \$this->workspace->id,",
    $content
);

$content = str_replace(
    "if (\$project && \$project->status !== \$newStatus)",
    "if (\$project && \$project->workspace_column_id !== (int)\$newStatus)",
    $content
);

$content = str_replace(
    "'status' => \$newStatus,",
    "'workspace_column_id' => (int)\$newStatus,",
    $content
);

// In Blade template
$content = str_replace(
    "@foreach(\$columns as \$statusKey => \$columnData)",
    "@foreach(\$columns as \$columnData)\n                @php \$statusKey = \$columnData['id']; @endphp",
    $content
);

$content = str_replace(
    "where('status', \$statusKey)",
    "where('workspace_column_id', \$statusKey)",
    $content
);

$content = str_replace(
    "\$columns[\$newStatus]['title']",
    "collect(\$this->columns)->firstWhere('id', \$newStatus)['title']",
    $content
);

$content = str_replace(
    "['status' => \$newStatus]",
    "['workspace_column_id' => (int)\$newStatus]",
    $content
);

$content = str_replace(
    "\$columns[\$selectedProject['status']]['title'] ?? \$selectedProject['status']",
    "collect(\$this->columns)->firstWhere('id', \$selectedProject['workspace_column_id'])['title'] ?? 'Unknown'",
    $content
);

file_put_contents($file, $content);
echo "Refactored board-kanban.blade.php\n";
