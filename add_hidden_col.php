<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

Schema::table('workspace_columns', function (Blueprint $table) {
    if (!Schema::hasColumn('workspace_columns', 'is_hidden')) {
        $table->boolean('is_hidden')->default(false);
    }
});
echo "Done\n";
