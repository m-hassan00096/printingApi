<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LengthAwarePaginator */
class RoleCollection extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator $roles */
        $roles = $this->resource;

        return [
            'roles' => RoleResource::collection($roles->getCollection())->resolve($request),
            'pagination' => [
                'currentPage' => $roles->currentPage(),
                'lastPage' => $roles->lastPage(),
                'perPage' => $roles->perPage(),
                'total' => $roles->total(),
            ],
        ];
    }
}
