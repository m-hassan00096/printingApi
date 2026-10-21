<?php

namespace App\Helpers;

use App\Enums\HttpStatusCode;
use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = '',
        HttpStatusCode $status = HttpStatusCode::OK
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status->value);
    }

    public static function error(
        string $message = '',
        array $errors = [],
        HttpStatusCode $status = HttpStatusCode::BAD_REQUEST
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status->value);
    }
}
