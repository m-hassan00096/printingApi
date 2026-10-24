<?php
use Illuminate\Support\Facades\Route;
use Modules\Deliveries\Http\Controllers\DeliveryController as Delivery;

Route::middleware('auth:sanctum')->prefix('v1/deliveries')->group(function (): void {
    Route::get('/ready', [Delivery::class, 'ready'])
        ->name('deliveries.ready.index')->middleware('permission:deliveries.ready.view');
    Route::get('/ready/{order}', [Delivery::class, 'preview'])
        ->whereNumber('order')->middleware('permission:deliveries.ready.view');
    Route::post('/orders/{order}/deliver', [Delivery::class, 'store'])
        ->whereNumber('order')->middleware('permission:deliveries.create');
    Route::get('/', [Delivery::class, 'index'])->middleware('permission:deliveries.list');
    Route::get('/{delivery}', [Delivery::class, 'show'])
        ->whereNumber('delivery')->middleware('permission:deliveries.view');
    Route::post('/{delivery}/cancel', [Delivery::class, 'cancel'])
        ->whereNumber('delivery')->middleware('permission:deliveries.cancel');
});
