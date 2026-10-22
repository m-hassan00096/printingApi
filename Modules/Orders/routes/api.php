<?php
use Illuminate\Support\Facades\Route;
use Modules\Orders\Http\Controllers\OrderActivityController;
use Modules\Orders\Http\Controllers\OrderController;
use Modules\Orders\Http\Controllers\OrderFileController;
use Modules\Orders\Http\Controllers\OrderFinanceController;
use Modules\Orders\Http\Controllers\OrderItemController;

Route::middleware('auth:sanctum')->prefix('v1/orders')->group(function (): void {
    Route::get('/', [OrderController::class, 'index'])->middleware('permission:orders.view');
    Route::post('/', [OrderController::class, 'store'])->middleware('permission:orders.create');
    Route::get('/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view');
    Route::match(['put', 'patch'], '/{order}', [OrderController::class, 'update'])
        ->middleware('permission:orders.update');
    Route::delete('/{order}', [OrderController::class, 'destroy'])->middleware('permission:orders.delete');

    Route::post('/{order}/items', [OrderItemController::class, 'store'])->middleware('permission:orders.update');
    Route::match(['put', 'patch'], '/{order}/items/{item}', [OrderItemController::class, 'update'])
        ->middleware('permission:orders.update');
    Route::delete('/{order}/items/{item}', [OrderItemController::class, 'destroy'])
        ->middleware('permission:orders.update');

    Route::post('/{order}/items/{item}/files', [OrderFileController::class, 'store'])
        ->middleware('permission:orders.update');
    Route::get('/{order}/items/{item}/files/{file}', [OrderFileController::class, 'download'])
        ->middleware('permission:orders.view');
    Route::delete('/{order}/items/{item}/files/{file}', [OrderFileController::class, 'destroy'])
        ->middleware('permission:orders.update');

    Route::get('/{order}/activities', OrderActivityController::class)->middleware('permission:orders.view');
    Route::post('/{order}/confirm', [OrderFinanceController::class, 'confirm'])
        ->middleware('permission:orders.confirm');
    Route::post('/{order}/cancel', [OrderFinanceController::class, 'cancel'])
        ->middleware('permission:orders.cancel');
});
