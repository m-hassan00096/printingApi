<?php

namespace Modules\PrintingServices\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\PrintingServices\Http\Requests\PricePreviewRequest;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Services\PriceResolver;

class PrintingServicePricePreviewController extends Controller
{
    public function __invoke(PricePreviewRequest $request, PriceResolver $resolver): JsonResponse
    {
        $data = $request->validated();
        $service = PrintingService::query()->findOrFail($data['serviceId']);

        return ApiResponse::success(
            data: $resolver->resolve(
                $service,
                isset($data['customerTypeId']) ? (int) $data['customerTypeId'] : null,
                (int) $data['quantity'],
            ),
        );
    }
}

