<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\Http\Requests\StoreCustomerSettlementRequest;
use Modules\Finance\Services\CustomerSettlementRecordService;

class CustomerSettlementRecordsController extends Controller
{
    public function index(int $customer, Request $request, CustomerSettlementRecordService $records): JsonResponse
    {
        $input = $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return ApiResponse::success(data: $records->index($customer, (int) ($input['page'] ?? 1)));
    }

    public function show(int $customer, int $settlement, CustomerSettlementRecordService $records): JsonResponse
    {
        return ApiResponse::success(data: $records->show($customer, $settlement));
    }

    public function store(int $customer, StoreCustomerSettlementRequest $request, CustomerSettlementRecordService $records): JsonResponse
    {
        $payload = $request->validated();
        if ((float) $payload['discountAmount'] > 0
            && ! $request->user()->can('finance.settlements.discount')) {
            abort(403);
        }
        if ((float) $payload['collectedAmount'] > 0
            && ! $request->user()->can('finance.collections.create')) {
            abort(403);
        }

        return ApiResponse::success(
            data: $records->create($customer, $payload, (int) $request->user()->id, $request->idempotencyHash()),
            message: __('finance.messages.settlement_created'),
            status: HttpStatusCode::CREATED,
        );
    }
}
