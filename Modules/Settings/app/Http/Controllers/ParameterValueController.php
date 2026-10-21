<?php

namespace Modules\Settings\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Modules\Settings\Actions\ParameterValues\CreateParameterValueAction;
use Modules\Settings\Http\Requests\ParameterValues\StoreParameterValueRequest;
use Modules\Settings\Http\Resources\ParameterValueResource;

class ParameterValueController extends Controller
{
    public function store(
        StoreParameterValueRequest $request,
        CreateParameterValueAction $createParameterValueAction,
    ): JsonResponse {
        $validated = $request->validated();

        $parameterValue = $createParameterValueAction->execute(
            parameterId: (int) $validated['parameterId'],
            data: Arr::except($validated, ['parameterId']),
        );

        return ApiResponse::success(
            data: (new ParameterValueResource($parameterValue))->resolve($request),
            status: HttpStatusCode::CREATED,
        );
    }
}
