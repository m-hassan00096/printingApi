<?php
namespace Modules\Finance\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\Http\Requests\RefundIndexRequest;
use Modules\Finance\Http\Requests\StoreRefundRequest;
use Modules\Finance\Http\Resources\RefundResource;
use Modules\Finance\Models\Refund;
use Modules\Finance\Services\RefundService;
use Modules\Finance\Services\LedgerService;
use Modules\Finance\Support\Money;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Finance\Services\IdempotencyService;

class RefundController extends Controller
{
    /** Advisory only: RefundService enforces available credit again under the customer lock. */
    public function creditPreview(int $customer, LedgerService $ledger, CustomerFinanceReference $customers): JsonResponse
    {
        if (! $customers->exists($customer)) {
            abort(404);
        }
        $cents = $ledger->cents($customer);
        return ApiResponse::success(data: [
            'customerId' => $customer,
            'availableCredit' => Money::format($cents < 0 ? -$cents : 0),
            'canRefund' => $cents < 0,
        ]);
    }

    public function index(RefundIndexRequest $request): JsonResponse
    {
        $filters = $request->validated('filter', []);
        $q = Refund::query();
        if (isset($filters['customerId'])) $q->where('customer_id', (int) $filters['customerId']);
        if (isset($filters['dateFrom'])) $q->whereDate('refunded_on', '>=', $filters['dateFrom']);
        if (isset($filters['dateTo'])) $q->whereDate('refunded_on', '<=', $filters['dateTo']);
        if (isset($filters['paymentMethod'])) $q->where('payment_method', $filters['paymentMethod']);
        if (! empty($filters['search'])) {
            $s = '%'.$filters['search'].'%';
            $q->where(fn ($where) => $where->where('refund_number', 'like', $s)
                ->orWhere('customer_name_snapshot', 'like', $s)->orWhere('customer_phone_snapshot', 'like', $s));
        }
        $p = $q->orderByDesc('id')->paginate((int) $request->input('perPage', 20));
        return ApiResponse::success(data: [
            'refunds' => RefundResource::collection($p->getCollection())->resolve($request),
            'pagination' => ['currentPage' => $p->currentPage(), 'lastPage' => $p->lastPage(),
                'perPage' => $p->perPage(), 'total' => $p->total()],
        ]);
    }

    public function show(Refund $refund, Request $request): JsonResponse
    {
        return ApiResponse::success(data: (new RefundResource($refund))->resolve($request));
    }

    public function store(StoreRefundRequest $request, RefundService $service): JsonResponse
    {
        $refund = $service->create($request->validated(), (int) $request->user()->id, $request->idempotencyHash());
        return ApiResponse::success(
            data: ['id' => $refund->id, 'refundNumber' => $refund->refund_number],
            message: __('finance.messages.refund_created'), status: HttpStatusCode::CREATED,
        );
    }
}
