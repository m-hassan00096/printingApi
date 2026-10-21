<?php

namespace Modules\Settings\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Settings\Actions\BankAccounts\CreateBankAccountAction;
use Modules\Settings\Actions\BankAccounts\DeleteBankAccountAction;
use Modules\Settings\Actions\BankAccounts\UpdateBankAccountAction;
use Modules\Settings\Http\Requests\BankAccounts\StoreBankAccountRequest;
use Modules\Settings\Http\Requests\BankAccounts\UpdateBankAccountRequest;
use Modules\Settings\Http\Requests\PaymentDestinations\IndexPaymentDestinationsRequest;
use Modules\Settings\Http\Resources\BankAccountCollection;
use Modules\Settings\Http\Resources\BankAccountResource;
use Modules\Settings\Models\BankAccount;
use Modules\Settings\Services\BankAccountQueryService;
use Modules\Settings\Services\PaymentDestinationDefaultService;

class BankAccountController extends Controller
{
    public function index(IndexPaymentDestinationsRequest $request, BankAccountQueryService $accounts): JsonResponse
    {
        return ApiResponse::success(
            data: (new BankAccountCollection($accounts->paginate($request)))->resolve($request),
        );
    }

    public function show(BankAccount $bankAccount): JsonResponse
    {
        return ApiResponse::success(
            data: (new BankAccountResource($bankAccount))->resolve(),
        );
    }

    public function store(
        StoreBankAccountRequest $request,
        CreateBankAccountAction $action,
        PaymentDestinationDefaultService $defaults,
    ): JsonResponse {
        $account = $action->execute($request->validated(), $defaults);

        return ApiResponse::success(
            data: (new BankAccountResource($account))->resolve($request),
            status: HttpStatusCode::CREATED,
        );
    }

    public function update(
        UpdateBankAccountRequest $request,
        BankAccount $bankAccount,
        UpdateBankAccountAction $action,
        PaymentDestinationDefaultService $defaults,
    ): JsonResponse {
        $account = $action->execute($bankAccount, $request->validated(), $defaults);

        return ApiResponse::success(
            data: (new BankAccountResource($account))->resolve($request),
        );
    }

    public function destroy(
        BankAccount $bankAccount,
        DeleteBankAccountAction $action,
        PaymentDestinationDefaultService $defaults,
    ): JsonResponse {
        $action->execute($bankAccount, $defaults);

        return ApiResponse::success();
    }
}
