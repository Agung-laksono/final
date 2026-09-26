<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

Schema::table('workspace_columns', function (Blueprint $table) {
    if (!Schema::hasColumn('workspace_columns', 'type')) {
        $table->string('type')->default('normal');
    }
});
echo "Done\n";
