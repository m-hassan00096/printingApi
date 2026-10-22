<?php
namespace Modules\Customers\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCollection extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator $customers */
        $customers = $this->resource;

        return [
            'customers' => CustomerResource::collection($customers->getCollection())->resolve($request),
            'pagination' => [
                'currentPage' => $customers->currentPage(),
                'lastPage' => $customers->lastPage(),
                'perPage' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ];
    }
}
