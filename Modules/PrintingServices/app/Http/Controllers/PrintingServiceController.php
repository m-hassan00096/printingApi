<?php

namespace Modules\PrintingServices\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\PrintingServices\Actions\CreatePrintingServiceAction;
use Modules\PrintingServices\Actions\DeletePrintingServiceAction;
use Modules\PrintingServices\Actions\UpdatePrintingServiceAction;
use Modules\PrintingServices\Http\Requests\IndexPrintingServicesRequest;
use Modules\PrintingServices\Http\Requests\StorePrintingServiceRequest;
use Modules\PrintingServices\Http\Requests\UpdatePrintingServiceRequest;
use Modules\PrintingServices\Http\Resources\PrintingServiceCollection;
use Modules\PrintingServices\Http\Resources\PrintingServiceResource;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Services\PrintingServiceQueryService;

class PrintingServiceController extends Controller
{
    public function index(IndexPrintingServicesRequest $request, PrintingServiceQueryService $service): JsonResponse
    {
        return ApiResponse::success(
            data: (new PrintingServiceCollection($service->paginate($request)))->resolve($request),
        );
    }

    public function show(PrintingService $printingService): JsonResponse
    {
        return ApiResponse::success(
            data: (new PrintingServiceResource($printingService->load('priceTiers')))->resolve(),
        );
    }

    public function store(StorePrintingServiceRequest $request, CreatePrintingServiceAction $action): JsonResponse
    {
        $service = $action->execute($request->validated());

        return ApiResponse::success(
            data: (new PrintingServiceResource($service))->resolve($request),
            status: HttpStatusCode::CREATED,
        );
    }

    public function update(
        UpdatePrintingServiceRequest $request,
        PrintingService $printingService,
        UpdatePrintingServiceAction $action,
    ): JsonResponse {
        $service = $action->execute($printingService, $request->validated());

        return ApiResponse::success(
            data: (new PrintingServiceResource($service))->resolve($request),
        );
    }

    public function destroy(PrintingService $printingService, DeletePrintingServiceAction $action): JsonResponse
    {
        $action->execute($printingService);

        return ApiResponse::success();
    }
}

