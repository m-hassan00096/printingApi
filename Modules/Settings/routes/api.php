<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\ParameterValueController;

Route::middleware('auth:sanctum')
    ->prefix('v1/settings')
    ->group(function (): void {
        Route::get('parameter-values', [ParameterValueController::class, 'index'])
            ->middleware('permission:settings.view_parameter_values');

        Route::get('parameter-values/{parameterValue}', [ParameterValueController::class, 'show'])
            ->middleware('permission:settings.view_parameter_values');

        Route::post('parameter-values', [ParameterValueController::class, 'store'])
            ->middleware('permission:settings.create_parameter_value');

        Route::match(['put', 'patch'], 'parameter-values/{parameterValue}', [ParameterValueController::class, 'update'])
            ->middleware('permission:settings.update_parameter_value');
    });
