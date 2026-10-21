<?php

use App\Http\Controllers\SelectController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->get('v1/selects', [SelectController::class, 'index']);
