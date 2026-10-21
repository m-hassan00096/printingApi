<?php

namespace Modules\Settings\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LengthAwarePaginator */
class EWalletCollection extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator $wallets */
        $wallets = $this->resource;

        return [
            'eWallets' => EWalletResource::collection($wallets->getCollection())->resolve($request),
            'pagination' => [
                'currentPage' => $wallets->currentPage(),
                'lastPage' => $wallets->lastPage(),
                'perPage' => $wallets->perPage(),
                'total' => $wallets->total(),
            ],
        ];
    }
}
