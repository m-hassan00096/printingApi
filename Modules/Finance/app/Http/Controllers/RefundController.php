<?php
namespace Modules\Finance\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\Http\Requests\FinanceIndexRequest;
use Modules\Finance\Http\Requests\StoreRefundRequest;
use Modules\Finance\Http\Resources\RefundResource;
use Modules\Finance\Models\Refund;
use Modules\Finance\Services\RefundService;
use Modules\Finance\Services\IdempotencyService;

class RefundController extends Controller
{
    public function index(FinanceIndexRequest $request): JsonResponse
    {
        $filters = $request->validated('filter', []);
        $q = Refund::query();
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

    public function store(StoreRefundRequest $request, RefundService $service, IdempotencyService $keys): JsonResponse
    {
        $refund = $service->create($request->validated(), (int) $request->user()->id, $keys->key($request));
        return ApiResponse::success(
            data: ['id' => $refund->id, 'refundNumber' => $refund->refund_number],
            message: __('finance.messages.refund_created'), status: HttpStatusCode::CREATED,
        );
    }
}
