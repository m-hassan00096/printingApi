<?php
namespace Modules\Deliveries\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\Deliveries\Http\Requests\DeliveryIndexRequest;
use Modules\Deliveries\Http\Requests\DeliverOrderRequest;
use Modules\Deliveries\Http\Requests\CancelDeliveryRequest;
use Modules\Deliveries\Services\DeliveryQueryService;
use Modules\Deliveries\Services\DeliveryWorkflowService;

class DeliveryController extends Controller
{
    public function ready(DeliveryIndexRequest $r, DeliveryQueryService $query): JsonResponse
    {
        return ApiResponse::success(data: $query->ready($r->validated('filter', []),
            $r->integer('page', 1), $r->integer('perPage', 20), $r->input('sort', 'dueDate'),
            $r->user()->can('deliveries.create'), $r->user()->can('finance.balances.view')));
    }

    public function preview(int $order, Request $r, DeliveryQueryService $query): JsonResponse
    {
        return ApiResponse::success(data: $query->preview($order,
            $r->user()->can('deliveries.create'), $r->user()->can('finance.collections.create'),
            $r->user()->can('finance.balances.view')));
    }

    public function store(int $order, DeliverOrderRequest $r, DeliveryWorkflowService $service): JsonResponse
    {
        $data = $r->validated();
        if (\Modules\Finance\Support\Money::cents($data['collectedAmount'] ?? '0.00') > 0
            && ! $r->user()->can('finance.collections.create')) abort(403);
        $service->deliver($order, $data, (int) $r->user()->id, $r->user()->name, $r->idempotencyHash());
        return ApiResponse::success(message: __('deliveries.messages.delivered'));
    }

    public function index(DeliveryIndexRequest $r, DeliveryQueryService $query): JsonResponse
    {
        return ApiResponse::success(data: $query->history($r->validated('filter', []),
            $r->integer('page', 1), $r->integer('perPage', 20), $r->input('sort', '-deliveredAt'),
            $r->user()->can('finance.collections.view'), $r->user()->can('deliveries.cancel')));
    }

    public function show(int $delivery, Request $r, DeliveryQueryService $query): JsonResponse
    {
        return ApiResponse::success(data: $query->show($delivery,
            $r->user()->can('finance.collections.view'), $r->user()->can('deliveries.cancel')));
    }

    public function cancel(int $delivery, CancelDeliveryRequest $r, DeliveryWorkflowService $service): JsonResponse
    {
        $service->cancel($delivery, $r->validated(), (int) $r->user()->id,
            $r->user()->name, $r->idempotencyHash());
        return ApiResponse::success(message: __('deliveries.messages.cancelled'));
    }
}
