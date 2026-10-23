<?php
namespace Modules\Orders\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Orders\Http\Requests\StoreOrderItemRequest;
use Modules\Orders\Http\Requests\UpdateOrderItemRequest;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Orders\Services\DraftOrderService;

class OrderItemController extends Controller
{
    public function store(StoreOrderItemRequest $request, Order $order, DraftOrderService $drafts): JsonResponse
    {
        $drafts->addItem($order, $request->validated());

        return ApiResponse::success(message: __('orders.messages.item_added'));
    }

    public function update(
        UpdateOrderItemRequest $request,
        Order $order,
        OrderItem $item,
        DraftOrderService $drafts,
    ): JsonResponse {
        $this->assertOwner($order, $item);
        $drafts->updateItem($order, $item->id, $request->validated());

        return ApiResponse::success(message: __('orders.messages.item_updated'));
    }

    public function destroy(Order $order, OrderItem $item, DraftOrderService $drafts): JsonResponse
    {
        $this->assertOwner($order, $item);
        $drafts->removeItem($order, $item->id);

        return ApiResponse::success(message: __('orders.messages.item_deleted'));
    }

    private function assertOwner(Order $order, OrderItem $item): void
    {
        abort_unless($item->order_id === $order->id, 404);
    }
}
