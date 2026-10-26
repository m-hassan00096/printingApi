<?php

use Illuminate\Support\Facades\Route;
use Modules\Orders\Http\Controllers\OrderActivityController;
use Modules\Orders\Http\Controllers\OrderController;
use Modules\Orders\Http\Controllers\DashboardController;
use Modules\Orders\Http\Controllers\OrderFileController;
use Modules\Orders\Http\Controllers\OrderFinanceController;
use Modules\Orders\Http\Controllers\OrderItemController;

// Dashboard is intentionally restricted to orders.list; other sections are linked only by client permissions.
Route::get('v1/dashboard', DashboardController::class)
    ->middleware(['auth:sanctum', 'permission:orders.list']);

Route::middleware('auth:sanctum')->prefix('v1/orders')->group(function (): void {
    Route::get('/', [OrderController::class, 'index'])->middleware('permission:orders.list');
    Route::post('/', [OrderController::class, 'store'])->middleware('permission:orders.create');
    Route::get('/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view');
    Route::match(['put', 'patch'], '/{order}', [OrderController::class, 'update'])
        ->middleware('permission:orders.update');
    Route::delete('/{order}', [OrderController::class, 'destroy'])
        ->middleware('permission:orders.delete');

    Route::post('/{order}/items', [OrderItemController::class, 'store'])
        ->middleware('permission:orders.items.create');
    Route::match(['put', 'patch'], '/{order}/items/{item}', [OrderItemController::class, 'update'])
        ->middleware('permission:orders.items.update');
    Route::delete('/{order}/items/{item}', [OrderItemController::class, 'destroy'])
        ->middleware('permission:orders.items.delete');

    Route::post('/{order}/items/{item}/files', [OrderFileController::class, 'store'])
        ->middleware('permission:orders.files.upload');
    Route::get('/{order}/items/{item}/files', [OrderFileController::class, 'index'])
        ->middleware('permission:orders.files.list');
    Route::get('/{order}/items/{item}/files/{file}', [OrderFileController::class, 'download'])
        ->middleware('permission:orders.files.view');
    Route::delete('/{order}/items/{item}/files/{file}', [OrderFileController::class, 'destroy'])
        ->middleware('permission:orders.files.delete');

    Route::get('/{order}/activities', OrderActivityController::class)
        ->middleware('permission:orders.activities.view');
    Route::post('/{order}/confirm', [OrderFinanceController::class, 'confirm'])
        ->middleware('permission:orders.confirm');
    Route::post('/{order}/cancel', [OrderFinanceController::class, 'cancel'])
        ->middleware('permission:orders.cancel');
});
