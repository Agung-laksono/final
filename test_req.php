<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/workspaces/2');
auth()->loginUsingId(1);
// Log the start
echo "Handling request...\n";
$response = $kernel->handle($request);
echo "Response status: " . $response->getStatusCode() . "\n";
