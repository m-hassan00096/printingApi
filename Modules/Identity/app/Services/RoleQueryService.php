<?php

namespace Modules\Identity\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Identity\Http\Requests\Roles\IndexRolesRequest;
use Modules\Identity\Models\Role;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class RoleQueryService
{
    public function paginate(IndexRolesRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(
            Role::query()
                ->where('guard_name', 'web')
                ->with('translations')
                ->withCount('users'),
            $request
        )
            ->allowedFilters(...[
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $search = (string) $value;
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhereHas('translations', function (Builder $translations) use ($search): void {
                                $translations->where('alias', 'like', "%{$search}%");
                            });
                    });
                }),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(...[
                'id', 'name', 'created_at',
                AllowedSort::field('usersCount', 'users_count'),
            ])
            ->defaultSort('-id')
            ->paginate($request->integer('perPage', 15))
            ->withQueryString();
    }

    public function find(Role $role): Role
    {
        abort_unless($role->guard_name === 'web', 404);

        return $role->loadMissing('translations', 'permissions')->loadCount('users');
    }
}
