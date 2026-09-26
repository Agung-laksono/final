<?php
$dir = __DIR__ . '/Modules/Workspace/database/migrations';

$files = scandir($dir);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    if ($file === '2026_09_18_021401_rename_marketing_tables_to_tasks.php') {
        unlink("$dir/$file");
        echo "Deleted $file\n";
        continue;
    }

    $filepath = "$dir/$file";
    if (is_file($filepath) && pathinfo($filepath, PATHINFO_EXTENSION) === 'php') {
        
        $newFile = $file;
        $newFile = str_replace('marketing_projects', 'tasks', $newFile);
        $newFile = str_replace('marketing_project_user', 'task_assignee', $newFile);
        $newFile = str_replace('marketing_project_label', 'task_label', $newFile);
        $newFile = str_replace('marketing_', 'task_', $newFile);

        $content = file_get_contents($filepath);
        
        // Replacements
        $content = str_replace("'marketing_projects'", "'tasks'", $content);
        $content = str_replace("'marketing_project_user'", "'task_assignee'", $content);
        $content = str_replace("'marketing_project_label'", "'task_label'", $content);
        $content = str_replace("'marketing_labels'", "'task_labels'", $content);
        $content = str_replace("'marketing_subtasks'", "'task_subtasks'", $content);
        $content = str_replace("'marketing_comments'", "'task_comments'", $content);
        $content = str_replace("'marketing_attachments'", "'task_attachments'", $content);
        $content = str_replace("'marketing_activities'", "'task_activities'", $content);
        $content = str_replace("'marketing_time_logs'", "'task_time_logs'", $content);
        $content = str_replace("'marketing_project_links'", "'task_links'", $content);
        
        $content = str_replace("'marketing_project_id'", "'task_id'", $content);
        $content = str_replace("'marketing_label_id'", "'task_label_id'", $content);

        // Put back content
        if ($file !== $newFile) {
            file_put_contents("$dir/$newFile", $content);
            unlink($filepath);
            echo "Renamed $file -> $newFile\n";
        } else {
            file_put_contents($filepath, $content);
            echo "Updated $file\n";
        }
    }
}
echo "Done refactoring migrations.\n";
