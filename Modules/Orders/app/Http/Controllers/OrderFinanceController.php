<?php
namespace Modules\Orders\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderFinanceGateway;
use Modules\Orders\Services\OrderConfirmationService;
use Modules\Orders\Services\OrderIdempotencyKey;

class OrderFinanceController extends Controller
{
    public function confirm(
        Request $request,
        Order $order,
        OrderConfirmationService $confirmation,
    ): JsonResponse {
        $data = $request->validate([
            'depositAmount' => [
                'sometimes', 'nullable', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D',
            ],
        ]);
        $key = OrderIdempotencyKey::required($request);
        $confirmation->confirm($order, $key, $data['depositAmount'] ?? null);

        return ApiResponse::success(message: __('orders.messages.confirmed'));
    }

    public function cancel(Request $request, Order $order, OrderFinanceGateway $finance): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:3000']]);
        $key = OrderIdempotencyKey::required($request);

        DB::transaction(function () use ($request, $order, $finance, $key, $data): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== OrderStatus::CONFIRMED || $locked->delivered_at !== null) {
                throw new OrdersBusinessException('cannot_cancel');
            }

            // Reversal of liability happens in Finance; deposit refunds never
            // happen here and require an explicit future Refund action.
            $finance->recordCancellation($locked, $key);

            $locked->status = OrderStatus::CANCELLED;
            $locked->cancelled_at = now();
            $locked->cancelled_by = $request->user()->getKey();
            $locked->cancellation_reason = $data['reason'];
            $locked->save();
        }, 3);

        return ApiResponse::success(message: __('orders.messages.cancelled'));
    }

}
