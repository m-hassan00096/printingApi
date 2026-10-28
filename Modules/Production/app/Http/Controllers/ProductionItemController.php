<?php
namespace Modules\Production\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Production\Http\Requests\ProductionIndexRequest;
use Modules\Production\Http\Requests\ProductionSummaryRequest;
use Modules\Production\Http\Requests\ProductionHistoryRequest;
use Modules\Production\Http\Requests\EmptyProductionRequest;
use Modules\Production\Http\Requests\PrintingLocationRequest;
use Modules\Production\Http\Requests\SendBatchRequest;
use Modules\Production\Services\ProductionQueryService;
use Modules\Production\Services\ProductionWorkflowService;

class ProductionItemController extends Controller
{
    public function index(ProductionIndexRequest $request, ProductionQueryService $query): JsonResponse
    {
        return ApiResponse::success(data: $query->items(
            $request->validated('filter', []), (int) $request->input('page', 1),
            (int) $request->input('perPage', 20), $request->input('sort', 'priority'),
            (int) $request->user()->getKey(),
        ));
    }

    public function summary(ProductionSummaryRequest $request, ProductionQueryService $query): JsonResponse
    {
        return ApiResponse::success(data: $query->summary(
            $request->validated('filter', []), (int) $request->user()->getKey(),
        ));
    }

    public function details(int $orderItem, ProductionQueryService $query): JsonResponse
    {
        return ApiResponse::success(data: $query->details($orderItem));
    }

    public function history(int $orderItem, ProductionHistoryRequest $request, ProductionQueryService $query): JsonResponse
    {
        return ApiResponse::success(data: $query->history(
            $orderItem, (int) $request->input('page', 1), (int) $request->input('perPage', 20),
        ));
    }

    public function startDesign(int $orderItem, EmptyProductionRequest $request, ProductionWorkflowService $service): JsonResponse
    {
        return $this->move([$orderItem], 'start_design', $request, $service);
    }

    public function completeDesign(int $orderItem, EmptyProductionRequest $request, ProductionWorkflowService $service): JsonResponse
    {
        return $this->move([$orderItem], 'complete_design', $request, $service);
    }

    public function completeAndSend(int $orderItem, PrintingLocationRequest $request, ProductionWorkflowService $service): JsonResponse
    {
        return $this->move([$orderItem], 'complete_and_send', $request, $service, (int) $request->validated('printingLocationId'));
    }

    public function send(int $orderItem, PrintingLocationRequest $request, ProductionWorkflowService $service): JsonResponse
    {
        return $this->move([$orderItem], 'send_to_printing', $request, $service, (int) $request->validated('printingLocationId'));
    }

    public function sendBatch(SendBatchRequest $request, ProductionWorkflowService $service): JsonResponse
    {
        $data = $request->validated();
        return $this->move($data['orderItemIds'], 'send_to_printing', $request, $service,
            (int) $data['printingLocationId']);
    }

    public function changeLocation(int $orderItem, PrintingLocationRequest $request, ProductionWorkflowService $service): JsonResponse
    {
        return $this->move([$orderItem], 'change_location', $request, $service, (int) $request->validated('printingLocationId'));
    }

    public function markReady(int $orderItem, EmptyProductionRequest $request, ProductionWorkflowService $service): JsonResponse
    {
        return $this->move([$orderItem], 'mark_ready', $request, $service);
    }

    public function undo(int $orderItem, EmptyProductionRequest $request, ProductionWorkflowService $service): JsonResponse
    {
        return $this->move([$orderItem], 'undo', $request, $service);
    }

    private function move(array $ids, string $action, \Modules\Production\Http\Requests\ProductionWriteRequest $request,
        ProductionWorkflowService $service, ?int $locationId = null): JsonResponse
    {
        $service->run($ids, $action, (int) $request->user()->getKey(),
            (string) $request->user()->name, $request->idempotencyHash(), $locationId);

        return ApiResponse::success(message: __('production.messages.'.$action));
    }
}
