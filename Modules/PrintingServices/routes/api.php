<?php

use Illuminate\Support\Facades\Route;
use Modules\PrintingServices\Http\Controllers\PrintingServiceController;
use Modules\PrintingServices\Http\Controllers\PrintingServicePricePreviewController;

Route::middleware('auth:sanctum')
    ->prefix('v1/printing-services')
    ->group(function (): void {
        Route::get('/', [PrintingServiceController::class, 'index'])
            ->middleware('permission:printing_services.view');
        Route::get('/{printingService}', [PrintingServiceController::class, 'show'])
            ->middleware('permission:printing_services.view');
        Route::post('/', [PrintingServiceController::class, 'store'])
            ->middleware('permission:printing_services.create');
        Route::match(['put', 'patch'], '/{printingService}', [PrintingServiceController::class, 'update'])
            ->middleware('permission:printing_services.update');
        Route::delete('/{printingService}', [PrintingServiceController::class, 'destroy'])
            ->middleware('permission:printing_services.delete');
        Route::post('/price-preview', PrintingServicePricePreviewController::class)
            ->middleware('permission:printing_services.price_preview.view');
    });

