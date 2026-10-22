<?php
use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\Controllers\CustomerController;

Route::middleware('auth:sanctum')->prefix('v1/customers')->group(function (): void {
    Route::get('/', [CustomerController::class, 'index'])->middleware('permission:customers.view');
    Route::get('/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
    Route::post('/', [CustomerController::class, 'store'])->middleware('permission:customers.create');
    Route::match(['put', 'patch'], '/{customer}', [CustomerController::class, 'update'])
        ->middleware('permission:customers.update');
    Route::delete('/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');
});
