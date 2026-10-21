<?php

use App\Exceptions\Handlers\BusinessExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Modules\Identity\Http\Middleware\EnsureAssignedRoleIsActive;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
        $middleware->appendToGroup(
            'api',
            EnsureAssignedRoleIsActive::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        BusinessExceptionHandler::register($exceptions);
        $exceptions->render(function (
            ValidationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: __('auth.validation_failed'),
                errors: $exception->errors(),
                status: HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        });
    })->create();
