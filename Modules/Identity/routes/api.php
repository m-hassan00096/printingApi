
<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\Auth\LoginController;
use Modules\Identity\Http\Controllers\Auth\LogoutController;
use Modules\Identity\Http\Controllers\Auth\MeController;
use Modules\Identity\Http\Controllers\UserController;
use Modules\Identity\Http\Controllers\RoleController;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {

        Route::post('login', LoginController::class)
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {

            Route::get('me', [MeController::class, 'show']);

            Route::post('logout', LogoutController::class);

        });

    });

    // Inside the existing Route::prefix('v1')->group(...):
    Route::middleware('auth:sanctum')->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:identity.users.view');

        Route::get('/{user}', [UserController::class, 'show'])
            ->middleware('permission:identity.users.view');

        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission:identity.users.create');

        Route::match(['put', 'patch'], '/{user}', [UserController::class, 'update'])
            ->middleware('permission:identity.users.update');

        Route::delete('/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:identity.users.delete');
    });

    Route::middleware('auth:sanctum')->prefix('roles')->group(function (): void {
        Route::get('/', [RoleController::class, 'index'])
            ->middleware('permission:identity.roles.view');
        Route::get('/{role}', [RoleController::class, 'show'])
            ->middleware('permission:identity.roles.view');
        Route::post('/', [RoleController::class, 'store'])
            ->middleware('permission:identity.roles.create');
        Route::match(['put', 'patch'], '/{role}', [RoleController::class, 'update'])
            ->middleware('permission:identity.roles.update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:identity.roles.delete');
    });



});


