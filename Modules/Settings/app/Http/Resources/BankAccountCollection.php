<?php

namespace Modules\Settings\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LengthAwarePaginator */
class BankAccountCollection extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator $accounts */
        $accounts = $this->resource;

        return [
            'bankAccounts' => BankAccountResource::collection($accounts->getCollection())->resolve($request),
            'pagination' => [
                'currentPage' => $accounts->currentPage(),
                'lastPage' => $accounts->lastPage(),
                'perPage' => $accounts->perPage(),
                'total' => $accounts->total(),
            ],
        ];
    }
}
