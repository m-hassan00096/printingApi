<?php

namespace Modules\Finance\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Finance\Http\Requests\CustomerSettlementRequest;
use Modules\Finance\Services\CustomerSettlementService;

class CustomerSettlementController extends Controller
{
    public function show(int $customer, CustomerSettlementRequest $request, CustomerSettlementService $settlements): JsonResponse
    {
        $dates = $request->validated();

        return ApiResponse::success(
            data: $settlements->statement($customer, $dates['dateFrom'], $dates['dateTo']),
        );
    }
}
