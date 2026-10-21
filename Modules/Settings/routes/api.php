<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\ParameterValueController;

Route::middleware('auth:sanctum')
    ->prefix('v1/settings')
    ->group(function (): void {
        Route::post('printing-locations', [ParameterValueController::class, 'store'])
            ->defaults('parameterName', 'printing_locations')
            ->middleware('permission:settings.printing_locations.create');

        Route::post('customer-types', [ParameterValueController::class, 'store'])
            ->defaults('parameterName', 'customer_types')
            ->middleware('permission:settings.customer_types.create');
    });
