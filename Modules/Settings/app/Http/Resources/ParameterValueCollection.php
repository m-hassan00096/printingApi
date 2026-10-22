<?php

namespace Modules\Settings\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LengthAwarePaginator */
class ParameterValueCollection extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator $parameters */
        $parameters = $this->resource;

        return [
            'parameters' => ParameterValueResource::collection($parameters->getCollection())->resolve($request),
            'pagination' => [
                'currentPage' => $parameters->currentPage(),
                'lastPage' => $parameters->lastPage(),
                'perPage' => $parameters->perPage(),
                'total' => $parameters->total(),
            ],
        ];
    }
}
