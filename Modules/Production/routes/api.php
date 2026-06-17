<?php

use Illuminate\Support\Facades\Route;
use Modules\Production\Http\Controllers\ProductionController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('productions', ProductionController::class)->names('production');
});
