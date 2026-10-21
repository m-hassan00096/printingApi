<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\ParameterValueController;

Route::middleware('auth:sanctum')
    ->prefix('v1/settings')
    ->group(function (): void {
        Route::post('parameter-values', [ParameterValueController::class, 'store'])
            ->middleware('permission:settings.create_parameter_value');
    });
