<?php
namespace Modules\Orders\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Orders\Http\Requests\IndexOrdersRequest;
use Modules\Orders\Http\Requests\StoreOrderRequest;
use Modules\Orders\Http\Requests\UpdateOrderRequest;
use Modules\Orders\Http\Resources\OrderCollection;
use Modules\Orders\Http\Resources\OrderResource;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\DraftOrderService;
use Modules\Orders\Services\OrderIdempotencyKey;
use Modules\Orders\Services\OrderLifecycleService;
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

    public function store(StoreOrderRequest $request, OrderLifecycleService $orders): JsonResponse
    {
        $data = $request->validated();
        $this->authorizeConfirmationWhenRequested($request, $data);
        $key = OrderIdempotencyKey::required($request);
        $order = $orders->create($data, $key);

        return ApiResponse::success(
            message: $order->status === OrderStatus::CONFIRMED
                ? __('orders.messages.confirmed')
                : __('orders.messages.draft_created'),
            status: HttpStatusCode::CREATED,
        );
    }

    public function update(UpdateOrderRequest $request, Order $order, OrderLifecycleService $orders): JsonResponse
    {
        $data = $request->validated();
        $this->authorizeConfirmationWhenRequested($request, $data);
        $key = (int) ($data['status'] ?? OrderStatus::DRAFT->value) === OrderStatus::CONFIRMED->value
            ? OrderIdempotencyKey::required($request)
            : null;
        $updated = $orders->update($order, $data, $key);

        return ApiResponse::success(
            message: $updated->status === OrderStatus::CONFIRMED
                ? __('orders.messages.confirmed')
                : __('orders.messages.draft_updated'),
        );
    }

    private function authorizeConfirmationWhenRequested(\Illuminate\Http\Request $request, array $data): void
    {
        if ((int) ($data['status'] ?? OrderStatus::DRAFT->value) === OrderStatus::CONFIRMED->value) {
            abort_unless($request->user()?->can('orders.confirm'), 403);
        }
    }

    public function destroy(Order $order, DraftOrderService $drafts): JsonResponse
    {
        $drafts->delete($order);

        return ApiResponse::success(message: __('orders.messages.draft_deleted'));
    }
}
