<?php

namespace Modules\PrintingServices\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintingServiceCollection extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator $services */
        $services = $this->resource;

        return [
            'printingServices' => PrintingServiceResource::collection($services->getCollection())->resolve($request),
            'pagination' => [
                'currentPage' => $services->currentPage(),
                'lastPage' => $services->lastPage(),
                'perPage' => $services->perPage(),
                'total' => $services->total(),
            ],
        ];
    }
}

