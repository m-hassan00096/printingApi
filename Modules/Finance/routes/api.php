<?php
use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\CustomerFinanceController;
use Modules\Finance\Http\Controllers\CollectionController;
use Modules\Finance\Http\Controllers\RefundController;

Route::middleware('auth:sanctum')->prefix('v1/finance')->group(function (): void {
    Route::get('customers/{customer}/balance', [CustomerFinanceController::class, 'balance'])
        ->middleware('permission:finance.balances.view');
    Route::get('customers/{customer}/ledger', [CustomerFinanceController::class, 'ledger'])
        ->middleware('permission:finance.ledger.view');
    Route::post('customers/{customer}/opening-balance', [CustomerFinanceController::class, 'openingBalance'])
        ->middleware('permission:finance.opening_balances.create');

    Route::get('collections/summary', [CollectionController::class, 'summary'])
        ->middleware('permission:finance.collections.view');
    Route::get('collections', [CollectionController::class, 'index'])
        ->middleware('permission:finance.collections.view');
    Route::post('collections', [CollectionController::class, 'store'])
        ->middleware('permission:finance.collections.create');
    Route::get('collections/{collection}', [CollectionController::class, 'show'])
        ->middleware('permission:finance.collections.view');
    Route::post('collections/{collection}/reverse', [CollectionController::class, 'reverse'])
        ->middleware('permission:finance.collections.reverse');

    Route::get('refunds', [RefundController::class, 'index'])
        ->middleware('permission:finance.refunds.view');
    Route::post('refunds', [RefundController::class, 'store'])
        ->middleware('permission:finance.refunds.create');
    Route::get('refunds/{refund}', [RefundController::class, 'show'])
        ->middleware('permission:finance.refunds.view');
});
