<?php

namespace Modules\Settings\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Settings\Actions\ParameterValues\CreateParameterValueAction;
use Modules\Settings\Http\Requests\ParameterValues\StoreParameterValueRequest;
use Modules\Settings\Http\Resources\ParameterValueResource;
use Modules\Settings\Models\Parameter;

class ParameterValueController extends Controller
{
    public function store(
        StoreParameterValueRequest $request,
        CreateParameterValueAction $createParameterValueAction,
    ): JsonResponse {
        $parameter = Parameter::query()
            ->where('name', $request->route('parameterName'))
            ->firstOrFail();

        $parameterValue = $createParameterValueAction->execute(
            parameterId: $parameter->id,
            data: $request->validated(),
        );

        return ApiResponse::success(
            data: (new ParameterValueResource($parameterValue))->resolve($request),
            status: HttpStatusCode::CREATED,
        );
    }
}
