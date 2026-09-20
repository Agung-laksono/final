<?php
$dir = "Modules/Marketing/database/migrations";
$files = scandir($dir);

$schema = [
    'marketing_projects' => "\$table->string('title');\n            \$table->text('description')->nullable();\n            \$table->string('status')->default('ide_backlog');\n            \$table->string('platform')->nullable();\n            \$table->string('budget')->nullable();\n            \$table->string('cover_image_path')->nullable();\n            \$table->string('priority')->default('medium');\n            \$table->date('start_date')->nullable();\n            \$table->date('due_date')->nullable();\n            \$table->timestamp('completed_at')->nullable();",
    'marketing_labels' => "\$table->string('name');\n            \$table->string('color');",
    'marketing_subtasks' => "\$table->foreignId('marketing_project_id')->constrained()->cascadeOnDelete();\n            \$table->string('title');\n            \$table->boolean('is_completed')->default(false);\n            \$table->boolean('requires_input')->default(false);\n            \$table->text('input_value')->nullable();",
    'marketing_comments' => "\$table->foreignId('marketing_project_id')->constrained()->cascadeOnDelete();\n            \$table->foreignId('user_id')->constrained()->cascadeOnDelete();\n            \$table->text('content');",
    'marketing_attachments' => "\$table->foreignId('marketing_project_id')->constrained()->cascadeOnDelete();\n            \$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();\n            \$table->string('file_path');\n            \$table->string('file_name');\n            \$table->string('file_type')->nullable();\n            \$table->integer('file_size')->nullable();",
    'marketing_activities' => "\$table->foreignId('marketing_project_id')->constrained()->cascadeOnDelete();\n            \$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();\n            \$table->string('action');\n            \$table->text('description')->nullable();",
    'marketing_time_logs' => "\$table->foreignId('marketing_project_id')->constrained()->cascadeOnDelete();\n            \$table->foreignId('user_id')->constrained()->cascadeOnDelete();\n            \$table->timestamp('start_time')->nullable();\n            \$table->timestamp('end_time')->nullable();\n            \$table->integer('duration_minutes')->nullable();\n            \$table->text('description')->nullable();",
    'marketing_project_links' => "\$table->foreignId('from_project_id')->constrained('marketing_projects')->cascadeOnDelete();\n            \$table->foreignId('to_project_id')->constrained('marketing_projects')->cascadeOnDelete();\n            \$table->string('relation_type');",
    'marketing_project_label' => "\$table->foreignId('marketing_project_id')->constrained()->cascadeOnDelete();\n            \$table->foreignId('marketing_label_id')->constrained()->cascadeOnDelete();",
    'marketing_project_user' => "\$table->foreignId('marketing_project_id')->constrained()->cascadeOnDelete();\n            \$table->foreignId('user_id')->constrained()->cascadeOnDelete();"
];

foreach ($files as $file) {
    if (strpos($file, '.php') === false) continue;
    foreach ($schema as $tableName => $cols) {
        if (strpos($file, "create_{$tableName}_table") !== false) {
            $path = "$dir/$file";
            $content = file_get_contents($path);
            $content = preg_replace('/\$table->id\(\);\n/', "\$table->id();\n            $cols\n", $content);
            file_put_contents($path, $content);
            echo "Updated $file\n";
        }
    }
}
