<?php

namespace Modules\Settings\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Settings\Actions\EWallets\CreateEWalletAction;
use Modules\Settings\Actions\EWallets\DeleteEWalletAction;
use Modules\Settings\Actions\EWallets\UpdateEWalletAction;
use Modules\Settings\Http\Requests\EWallets\StoreEWalletRequest;
use Modules\Settings\Http\Requests\EWallets\UpdateEWalletRequest;
use Modules\Settings\Http\Requests\PaymentDestinations\IndexPaymentDestinationsRequest;
use Modules\Settings\Http\Resources\EWalletCollection;
use Modules\Settings\Http\Resources\EWalletResource;
use Modules\Settings\Models\EWallet;
use Modules\Settings\Services\EWalletQueryService;
use Modules\Settings\Services\PaymentDestinationDefaultService;

class EWalletController extends Controller
{
    public function index(IndexPaymentDestinationsRequest $request, EWalletQueryService $wallets): JsonResponse
    {
        return ApiResponse::success(
            data: (new EWalletCollection($wallets->paginate($request)))->resolve($request),
        );
    }

    public function show(EWallet $eWallet): JsonResponse
    {
        return ApiResponse::success(
            data: (new EWalletResource($eWallet))->resolve(),
        );
    }

    public function store(
        StoreEWalletRequest $request,
        CreateEWalletAction $action,
        PaymentDestinationDefaultService $defaults,
    ): JsonResponse {
        $wallet = $action->execute($request->validated(), $defaults);

        return ApiResponse::success(
            data: (new EWalletResource($wallet))->resolve($request),
            status: HttpStatusCode::CREATED,
        );
    }

    public function update(
        UpdateEWalletRequest $request,
        EWallet $eWallet,
        UpdateEWalletAction $action,
        PaymentDestinationDefaultService $defaults,
    ): JsonResponse {
        $wallet = $action->execute($eWallet, $request->validated(), $defaults);

        return ApiResponse::success(
            data: (new EWalletResource($wallet))->resolve($request),
        );
    }

    public function destroy(
        EWallet $eWallet,
        DeleteEWalletAction $action,
        PaymentDestinationDefaultService $defaults,
    ): JsonResponse {
        $action->execute($eWallet, $defaults);

        return ApiResponse::success();
    }
}
