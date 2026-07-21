<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

\Illuminate\Support\Facades\Auth::loginUsingId(1);
$request = Illuminate\Http\Request::create('/sales/orders/create', 'GET');
$response = $kernel->handle($request);
$content = $response->getContent();
file_put_contents('output_create.html', $content);
echo "HTML Size: " . strlen($content) . " bytes\n";
echo "Peak Memory Usage: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
