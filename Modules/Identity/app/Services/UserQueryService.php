<?php

namespace Modules\Identity\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Identity\Http\Requests\Users\IndexUsersRequest;
use Modules\Identity\Models\User;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserQueryService
{
    public function paginate(IndexUsersRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(User::query(), $request)
            ->allowedFilters(...[
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $search = is_array($value) ? implode(',', $value) : (string) $value;

                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                }),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('role', function (Builder $query, mixed $value): void {
                    $roleNames = is_array($value) ? $value : [$value];

                    $query->whereHas('roles', function (Builder $roles) use ($roleNames): void {
                        $roles->whereIn('name', $roleNames)
                            ->where('guard_name', 'web');
                    });
                }),
            ])
            ->allowedSorts(...['id', 'name', 'username', 'created_at'])
            ->defaultSort('-id')
            ->with('roles.translations')
            ->paginate($request->integer('perPage', 15))
            ->withQueryString();
    }

    public function find(User $user): User
    {
        return $user->loadMissing('roles.translations');
    }
}
