<?php
namespace Modules\Orders\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Orders\Http\Requests\IndexOrdersRequest;
use Modules\Orders\Http\Requests\StoreOrderRequest;
use Modules\Orders\Http\Requests\UpdateOrderRequest;
use Modules\Orders\Http\Resources\OrderCollection;
use Modules\Orders\Http\Resources\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\DraftOrderService;
use Modules\Orders\Services\OrderDraftIdempotencyService;
use Modules\Orders\Services\OrderQueryService;

class OrderController extends Controller
{
    public function index(IndexOrdersRequest $request, OrderQueryService $query): JsonResponse
    {
        return ApiResponse::success(
            data: (new OrderCollection($query->paginate($request)))->resolve($request),
        );
    }

    public function show(Order $order): JsonResponse
    {
        return ApiResponse::success(
            data: (new OrderResource($order->load(['customer', 'items.files'])))->resolve(),
        );
    }

    public function store(StoreOrderRequest $request, OrderDraftIdempotencyService $drafts): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '' || strlen($key) > 128) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => __('orders.messages.idempotency_required'),
            ]);
        }

        $order = $drafts->create($request->validated(), $key);

        return ApiResponse::success(
            data: (new OrderResource($order))->resolve($request),
            message: __('orders.messages.draft_created'),
            status: HttpStatusCode::CREATED,
        );
    }

    public function update(UpdateOrderRequest $request, Order $order, DraftOrderService $drafts): JsonResponse
    {
        $updated = $drafts->update($order, $request->validated());

        return ApiResponse::success(
            data: (new OrderResource($updated))->resolve($request),
            message: __('orders.messages.draft_updated'),
        );
    }

    public function destroy(Order $order, DraftOrderService $drafts): JsonResponse
    {
        $drafts->delete($order);

        return ApiResponse::success(message: __('orders.messages.draft_deleted'));
    }
}
