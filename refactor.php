<?php

$src = __DIR__ . '/Modules/Marketing';
$dst = __DIR__ . '/Modules/Workspace';

function rcopy($src, $dst) {
  if (file_exists($dst)) return false;
  if (is_dir($src)) {
    mkdir($dst);
    $files = scandir($src);
    foreach ($files as $file)
    if ($file != "." && $file != "..") rcopy("$src/$file", "$dst/$file");
  }
  else if (file_exists($src)) copy($src, $dst);
}

rcopy($src, $dst);
echo "Copied folder\n";

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dst));
foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    
    // Rename file if it contains Marketing
    $basename = basename($path);
    if (strpos($basename, 'MarketingProject') !== false) {
        $newBasename = str_replace('MarketingProject', 'Task', $basename);
        $newPath = dirname($path) . '/' . $newBasename;
        rename($path, $newPath);
        $path = $newPath;
    } elseif (strpos($basename, 'Marketing') !== false) {
        $newBasename = str_replace('Marketing', 'Task', $basename);
        if ($basename === 'MarketingController.php') {
            $newBasename = 'WorkspaceController.php';
        }
        $newPath = dirname($path) . '/' . $newBasename;
        rename($path, $newPath);
        $path = $newPath;
    }
    
    if (pathinfo($path, PATHINFO_EXTENSION) == 'php' || pathinfo($path, PATHINFO_EXTENSION) == 'json' || pathinfo($path, PATHINFO_EXTENSION) == 'blade.php') {
        $content = file_get_contents($path);
        
        $original = $content;
        
        $content = str_replace('Modules\Marketing', 'Modules\Workspace', $content);
        $content = str_replace('Modules/Marketing', 'Modules/Workspace', $content);
        $content = str_replace('marketing::', 'workspace::', $content);
        $content = str_replace('marketing.', 'workspace.', $content);
        
        $content = str_replace('MarketingProject', 'Task', $content);
        $content = str_replace('MarketingSubtask', 'TaskSubtask', $content);
        $content = str_replace('MarketingComment', 'TaskComment', $content);
        $content = str_replace('MarketingAttachment', 'TaskAttachment', $content);
        $content = str_replace('MarketingActivity', 'TaskActivity', $content);
        $content = str_replace('MarketingTimeLog', 'TaskTimeLog', $content);
        $content = str_replace('MarketingLabel', 'TaskLabel', $content);
        
        $content = str_replace('marketing_project_id', 'task_id', $content);
        $content = str_replace('MarketingController', 'WorkspaceController', $content);
        
        if ($content !== $original) {
            file_put_contents($path, $content);
        }
    }
}

// Rename livewire view folder
if (is_dir("$dst/resources/views/livewire/marketing")) {
    rename("$dst/resources/views/livewire/marketing", "$dst/resources/views/livewire/workspace");
}

echo "Done refactoring.\n";
