<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\Select\IndexSelectsRequest;
use App\Services\Select\SelectService;
use Illuminate\Http\JsonResponse;

class SelectController extends Controller
{
    public function index(IndexSelectsRequest $request, SelectService $selects): JsonResponse
    {
        return ApiResponse::success(
            data: $selects->getSelects(
                selects: $request->validated('selects'),
                parameters: $request->validated('params', []),
            ),
        );
    }
}
