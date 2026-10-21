<?php

namespace Modules\Settings\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Modules\Settings\Actions\ParameterValues\CreateParameterValueAction;
use Modules\Settings\Actions\ParameterValues\UpdateParameterValueAction;
use Modules\Settings\Http\Requests\ParameterValues\IndexParameterValuesRequest;
use Modules\Settings\Http\Requests\ParameterValues\StoreParameterValueRequest;
use Modules\Settings\Http\Requests\ParameterValues\UpdateParameterValueRequest;
use Modules\Settings\Http\Resources\ParameterValueCollection;
use Modules\Settings\Http\Resources\ParameterValueResource;
use Modules\Settings\Models\ParameterValue;
use Modules\Settings\Services\ParameterValueQueryService;

class ParameterValueController extends Controller
{
    public function index(
        IndexParameterValuesRequest $request,
        ParameterValueQueryService $parameters,
    ): JsonResponse {
        return ApiResponse::success(
            data: (new ParameterValueCollection($parameters->paginate($request)))->resolve($request),
        );
    }

    public function show(
        ParameterValue $parameterValue,
        ParameterValueQueryService $parameters,
    ): JsonResponse {
        return ApiResponse::success(
            data: (new ParameterValueResource($parameters->find($parameterValue)))->resolve(),
        );
    }

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

    public function update(
        UpdateParameterValueRequest $request,
        ParameterValue $parameterValue,
        UpdateParameterValueAction $updateParameterValueAction,
    ): JsonResponse {
        $parameterValue = $updateParameterValueAction->execute(
            $parameterValue,
            $request->validated(),
        );

        return ApiResponse::success(
            data: (new ParameterValueResource($parameterValue))->resolve($request),
        );
    }
}
