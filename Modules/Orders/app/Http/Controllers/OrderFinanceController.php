<?php
namespace Modules\Orders\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderFinanceGateway;
use Modules\Orders\Services\OrderNumberGenerator;

class OrderFinanceController extends Controller
{
    public function confirm(
        Request $request,
        Order $order,
        OrderFinanceGateway $finance,
        OrderNumberGenerator $numbers,
    ): JsonResponse {
        $data = $request->validate([
            'depositAmount' => [
                'sometimes', 'nullable', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D',
            ],
        ]);
        $key = $this->idempotencyKey($request);

        DB::transaction(function () use ($order, $finance, $numbers, $key, $data): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== OrderStatus::DRAFT) {
                throw new OrdersBusinessException('only_drafts_editable');
            }
            if ($locked->items()->count() < 1) {
                throw new OrdersBusinessException('last_item_required');
            }

            // Default gateway raises 409 and rolls back everything. Finance must
            // eventually replace it with a transactional ledger adapter.
            $finance->recordConfirmation($locked, $key, $data['depositAmount'] ?? null);

            $locked->order_number = $numbers->next();
            $locked->status = OrderStatus::CONFIRMED;
            $locked->production_status = \Modules\Orders\Enums\OrderProductionStatus::NOT_STARTED;
            $locked->confirmed_at = now();
            $locked->save();
        }, 3);

        return ApiResponse::success(message: __('orders.messages.confirmed'));
    }

    public function cancel(Request $request, Order $order, OrderFinanceGateway $finance): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:3000']]);
        $key = $this->idempotencyKey($request);

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

    private function idempotencyKey(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '' || strlen($key) > 128) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => __('orders.messages.idempotency_required'),
            ]);
        }

        return $key;
    }
}
