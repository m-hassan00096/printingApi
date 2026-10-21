<?php

namespace App\Exceptions\Handlers;

use App\Exceptions\BusinessException;
use App\Helpers\ApiResponse;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;

final class BusinessExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(
            function (
                BusinessException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    message: $exception->getMessage(),
                    errors: $exception->errors,
                    status: $exception->status,
                );
            }
        );
    }
}
