<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\Http\Requests\CreateAccountSettlementRequest;
use Modules\Finance\Services\AccountSettlementService;

class AccountSettlementController extends Controller
{
    public function preview(int $customer, Request $request, AccountSettlementService $service): JsonResponse
    {
        $data = $request->validate(['dateTo' => ['required', 'date_format:Y-m-d',
            'before_or_equal:'.now('Africa/Cairo')->toDateString()]]);
        return ApiResponse::success(data: $service->preview($customer, $data['dateTo']));
    }

    public function index(int $customer, Request $request, AccountSettlementService $service): JsonResponse
    {
        $data = $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        return ApiResponse::success(data: $service->history($customer, (int) ($data['page'] ?? 1)));
    }

    public function store(int $customer, CreateAccountSettlementRequest $request, AccountSettlementService $service): JsonResponse
    {
        return ApiResponse::success(data: $service->confirm($customer, $request->validated(),
            (int) $request->user()->id, $request->idempotencyHash()),
            message: __('settlements.saved'), status: HttpStatusCode::CREATED);
    }

    public function show(int $customer, int $settlement, AccountSettlementService $service): JsonResponse
    {
        return ApiResponse::success(data: $service->show($customer, $settlement));
    }
}
