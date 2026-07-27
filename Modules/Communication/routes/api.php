<?php

use Illuminate\Support\Facades\Route;
use Modules\Communication\Http\Controllers\CommunicationController;
use Modules\Communication\Http\Controllers\FonnteWebhookController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
// Route::apiResource('communications', CommunicationController::class)->names('communication');
});

// Fonnte Webhook endpoint (biasanya tidak pakai auth sanctum karena diakses oleh server Fonnte)
// Webhook Fonnte untuk update status (terkirim, dibaca) & pesan masuk
Route::any('/fonnte/webhook', [FonnteWebhookController::class, 'handle'])->name('fonnte.webhook');
