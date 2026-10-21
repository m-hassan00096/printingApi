<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LengthAwarePaginator */
class UserCollection extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator $users */
        $users = $this->resource;

        return [
            'users' => UserResource::collection($users->getCollection())->resolve($request),
            'pagination' => [
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
                'perPage' => $users->perPage(),
                'total' => $users->total(),
            ],
        ];
    }
}
