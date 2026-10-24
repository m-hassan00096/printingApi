<?php
namespace Modules\Finance\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Orders\Contracts\OrderFinanceReference;
use Modules\Finance\Enums\CollectionStatus;
use Modules\Finance\Enums\PaymentMethod;
use Modules\Finance\Http\Requests\FinanceIndexRequest;
use Modules\Finance\Http\Requests\StoreCollectionRequest;
use Modules\Finance\Http\Requests\ReverseCollectionRequest;
use Modules\Finance\Http\Resources\CollectionResource;
use Modules\Finance\Models\Collection;
use Modules\Finance\Services\CollectionService;
use Modules\Finance\Services\IdempotencyService;

class CollectionController extends Controller
{
    public function index(FinanceIndexRequest $request): JsonResponse
    {
        $p = $this->query($request)->paginate((int) $request->input('perPage', 20));
        return ApiResponse::success(data: [
            'collections' => CollectionResource::collection($p->getCollection())->resolve($request),
            'pagination' => ['currentPage' => $p->currentPage(), 'lastPage' => $p->lastPage(),
                'perPage' => $p->perPage(), 'total' => $p->total()],
        ]);
    }

    public function summary(FinanceIndexRequest $request): JsonResponse
    {
        $grouped = $this->query($request)->where('status', CollectionStatus::POSTED->value)
            ->reorder()->select('payment_method')->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('COUNT(*) as aggregate_count')->groupBy('payment_method')->get();
        $totals = [0 => 0, 1 => 0, 2 => 0];
        $count = 0;
        foreach ($grouped as $group) {
            $totals[$group->payment_method->value] = \Modules\Finance\Support\Money::cents((string) $group->total_amount);
            $count += (int) $group->aggregate_count;
        }
        return ApiResponse::success(data: [
            'totalCollected' => \Modules\Finance\Support\Money::format(array_sum($totals)),
            'collectionsCount' => $count,
            'cashTotal' => \Modules\Finance\Support\Money::format($totals[0]),
            'bankTotal' => \Modules\Finance\Support\Money::format($totals[1]),
            'walletTotal' => \Modules\Finance\Support\Money::format($totals[2]),
        ]);
    }

    public function show(Collection $collection, Request $request): JsonResponse
    {
        return ApiResponse::success(data: (new CollectionResource($collection))->resolve($request));
    }

    public function store(StoreCollectionRequest $request, CollectionService $service): JsonResponse
    {
        $key = $request->idempotencyHash();
        $collection = $service->create($request->validated(), (int) $request->user()->id, $key);
        return ApiResponse::success(
            data: ['id' => $collection->id, 'receiptNumber' => $collection->receipt_number],
            message: __('finance.messages.collection_created'), status: HttpStatusCode::CREATED,
        );
    }

    public function reverse(Collection $collection, ReverseCollectionRequest $request, CollectionService $service): JsonResponse
    {
        $data = $request->validated();
        $service->reverse($collection, $data['reason'], (int) $request->user()->id, $request->idempotencyHash());
        return ApiResponse::success(message: __('finance.messages.collection_reversed'));
    }

    private function query(FinanceIndexRequest $request): \Illuminate\Database\Eloquent\Builder
    {
        $filters = $request->validated('filter', []);
        $q = Collection::query();
        if (isset($filters['dateFrom'])) $q->whereDate('collected_on', '>=', $filters['dateFrom']);
        if (isset($filters['dateTo'])) $q->whereDate('collected_on', '<=', $filters['dateTo']);
        if (isset($filters['paymentMethod'])) $q->where('payment_method', $filters['paymentMethod']);
        if (isset($filters['source'])) $q->where('source', $filters['source']);
        if (isset($filters['status'])) $q->where('status', $filters['status']);
        if (! empty($filters['search'])) {
            $needle = $filters['search'];
            $matchingOrders = app(OrderFinanceReference::class)->findOrderIdsByNumber($needle);
            $q->where(function ($where) use ($needle, $matchingOrders): void {
                $where->where('receipt_number', 'like', '%'.$needle.'%')
                    ->orWhere('customer_name_snapshot', 'like', '%'.$needle.'%')
                    ->orWhere('customer_phone_snapshot', 'like', '%'.$needle.'%');
                if ($matchingOrders !== []) $where->orWhereIn('order_id', $matchingOrders);
            });
        }
        $sort = $request->input('sort', '-id');
        $sortMap = ['id' => ['id', 'asc'], '-id' => ['id', 'desc'],
            'collectedOn' => ['collected_on', 'asc'], '-collectedOn' => ['collected_on', 'desc']];
        [$col, $dir] = $sortMap[$sort] ?? ['id', 'desc'];
        return $q->orderBy($col, $dir)->orderBy('id', 'desc');
    }
}
