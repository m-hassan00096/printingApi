<?php
namespace Modules\Orders\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Orders\Http\Requests\StoreOrderItemRequest;
use Modules\Orders\Http\Requests\UpdateOrderItemRequest;
use Modules\Orders\Http\Resources\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Orders\Services\DraftOrderService;

class OrderItemController extends Controller
{
    public function store(StoreOrderItemRequest $request, Order $order, DraftOrderService $drafts): JsonResponse
    {
        $updated = $drafts->addItem($order, $request->validated());

        return ApiResponse::success(
            data: (new OrderResource($updated))->resolve($request),
            message: __('orders.messages.item_added'),
        );
    }

    public function update(
        UpdateOrderItemRequest $request,
        Order $order,
        OrderItem $item,
        DraftOrderService $drafts,
    ): JsonResponse {
        $this->assertOwner($order, $item);
        $updated = $drafts->updateItem($order, $item->id, $request->validated());

        return ApiResponse::success(
            data: (new OrderResource($updated))->resolve($request),
            message: __('orders.messages.item_updated'),
        );
    }

    public function destroy(Order $order, OrderItem $item, DraftOrderService $drafts): JsonResponse
    {
        $this->assertOwner($order, $item);
        $updated = $drafts->removeItem($order, $item->id);

        return ApiResponse::success(
            data: (new OrderResource($updated))->resolve(),
            message: __('orders.messages.item_deleted'),
        );
    }

    private function assertOwner(Order $order, OrderItem $item): void
    {
        abort_unless($item->order_id === $order->id, 404);
    }
}
