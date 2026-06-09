<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = app('livewire')->new('item-history-movement.movement-list');
dump(\Livewire\Drawer\Utils::getPublicMethodsDefinedBySubClass($c));
