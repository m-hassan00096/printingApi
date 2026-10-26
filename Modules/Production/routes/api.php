<?php
use Illuminate\Support\Facades\Route;
use Modules\Production\Http\Controllers\ProductionItemController as Items;

Route::middleware('auth:sanctum')->prefix('v1/production')->group(function (): void {
    Route::get('/items', [Items::class, 'index'])->middleware('permission:production.items.list');
    Route::get('/summary', [Items::class, 'summary'])->middleware('permission:production.summary.view');
    Route::post('/items/send-to-printing', [Items::class, 'sendBatch'])->middleware('permission:production.printing.send');

    Route::get('/items/{orderItem}/details', [Items::class, 'details'])
        ->whereNumber('orderItem')->middleware('permission:production.items.list');
    Route::get('/items/{orderItem}/history', [Items::class, 'history'])
        ->whereNumber('orderItem')->middleware('permission:production.history.view');
    Route::post('/items/{orderItem}/start-design', [Items::class, 'startDesign'])
        ->whereNumber('orderItem')->middleware('permission:production.design.start');
    Route::post('/items/{orderItem}/complete-design', [Items::class, 'completeDesign'])
        ->whereNumber('orderItem')->middleware('permission:production.design.complete');
    Route::post('/items/{orderItem}/complete-and-send-to-printing', [Items::class, 'completeAndSend'])
        ->whereNumber('orderItem')->middleware([
            'permission:production.design.complete', 'permission:production.printing.send',
        ]);
    Route::post('/items/{orderItem}/send-to-printing', [Items::class, 'send'])
        ->whereNumber('orderItem')->middleware('permission:production.printing.send');
    Route::patch('/items/{orderItem}/printing-location', [Items::class, 'changeLocation'])
        ->whereNumber('orderItem')->middleware('permission:production.printing.change_location');
    Route::post('/items/{orderItem}/mark-ready', [Items::class, 'markReady'])
        ->whereNumber('orderItem')->middleware('permission:production.printing.complete');
    Route::post('/items/{orderItem}/undo', [Items::class, 'undo'])
        ->whereNumber('orderItem')->middleware('permission:production.items.undo');
});
