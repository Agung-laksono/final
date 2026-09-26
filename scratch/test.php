<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "App booted.\n";

try {
    $workspace = \Modules\Workspace\Models\Workspace::find(4);
    echo "Workspace loaded: " . $workspace->name . "\n";

    echo "Loading objectives...\n";
    $objectives = $workspace->objectives()->with(['keyResults.owner', 'owner'])->get();
    echo "Objectives loaded: " . $objectives->count() . "\n";

    echo "Calculating progress...\n";
    foreach ($objectives as $objective) {
        echo "Obj: " . $objective->id . " Progress: " . $objective->progress . "\n";
        foreach ($objective->keyResults as $kr) {
            echo "  - KR: " . $kr->id . " Progress: " . $kr->progress . "\n";
        }
    }
    
    echo "Loading users...\n";
    $users = $workspace->users;
    echo "Users loaded: " . $users->count() . "\n";

    echo "Done.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
