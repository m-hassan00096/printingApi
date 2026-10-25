<?php

namespace Modules\Settings\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Settings\Models\Parameter;

class ParameterGroupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $groups = Parameter::query()
            ->whereIn('name', ['customer_types', 'printing_locations'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Parameter $parameter): array => [
                'id' => $parameter->id,
                'name' => $parameter->name,
            ])
            ->values()
            ->all();

        return ApiResponse::success(data: ['groups' => $groups]);
    }
}
