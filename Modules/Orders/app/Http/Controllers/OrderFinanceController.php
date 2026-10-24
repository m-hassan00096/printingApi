<?php
namespace Modules\Orders\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Contracts\CancelOrderFinanceData;
use Modules\Orders\Http\Requests\ConfirmOrderRequest;
use Modules\Orders\Http\Requests\CancelOrderRequest;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderFinanceGateway;
use Modules\Orders\Services\OrderConfirmationService;
use Modules\Orders\Services\OrderIdempotencyKey;

class OrderFinanceController extends Controller
{
    public function confirm(ConfirmOrderRequest $request, Order $order, OrderConfirmationService $confirmation): JsonResponse
    {
        $data = $request->validated();
        $key = OrderIdempotencyKey::required($request);
        $confirmation->confirm($order, $key, $data['depositAmount'] ?? null,
            $data['paymentMethod'] ?? null, $data['paymentDestinationId'] ?? null);

        return ApiResponse::success(message: __('orders.messages.confirmed'));
    }

    public function cancel(CancelOrderRequest $request, Order $order, OrderFinanceGateway $finance): JsonResponse
    {
        $data = $request->validated();
        $key = OrderIdempotencyKey::required($request);
        DB::transaction(function () use ($request, $order, $finance, $key, $data): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== OrderStatus::CONFIRMED || $locked->delivered_at !== null) {
                throw new OrdersBusinessException('cannot_cancel');
            }
            $finance->recordCancellation(new CancelOrderFinanceData(
                orderId: $locked->id, customerId: $locked->customer_id,
                totalAmount: (string) $locked->total_amount, idempotencyKey: $key,
                actorId: $request->user()->getKey(),
            ));
            $locked->status = OrderStatus::CANCELLED;
            $locked->cancelled_at = now();
            $locked->cancelled_by = $request->user()->getKey();
            $locked->cancellation_reason = $data['reason'];
            $locked->save();
        }, 3);
        return ApiResponse::success(message: __('orders.messages.cancelled'));
    }
}
