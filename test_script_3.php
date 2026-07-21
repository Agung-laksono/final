<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

\Illuminate\Support\Facades\DB::enableQueryLog();
\Illuminate\Support\Facades\Auth::loginUsingId(1);

$request = Illuminate\Http\Request::create('/sales/orders/create', 'GET');
$response = $kernel->handle($request);
echo "Status Code: " . $response->getStatusCode() . "\n";
$queries = \Illuminate\Support\Facades\DB::getQueryLog();
echo "Total Queries: " . count($queries) . "\n";
