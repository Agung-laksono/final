<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$response = Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
    'Authorization' => env('FONNTE_TOKEN')
])->post('https://api.fonnte.com/send', [
    'target' => env('FONNTE_TARGET', '08123456789'), // I will just use a dummy target
    'message' => 'Test webhook payload',
    'countryCode' => '62'
]);

var_dump($response->json());
