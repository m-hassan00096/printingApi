<?php

use Illuminate\Support\Facades\Route;
use Modules\Orders\Http\Controllers\OrderFilePreviewController;

// Publicly reachable only with a valid, expiring signature. Keep this route
// outside /api and auth:sanctum so <img> and <iframe> work without Axios.
Route::get('/preview/orders/files/{file}', OrderFilePreviewController::class)
    ->middleware('signed')
    ->name('orders.files.preview');
