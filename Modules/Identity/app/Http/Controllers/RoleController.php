<?php

namespace Modules\Identity\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Identity\Actions\Roles\CreateRoleAction;
use Modules\Identity\Actions\Roles\DeleteRoleAction;
use Modules\Identity\Actions\Roles\UpdateRoleAction;
use Modules\Identity\Http\Requests\Roles\IndexRolesRequest;
use Modules\Identity\Http\Requests\Roles\StoreRoleRequest;
use Modules\Identity\Http\Requests\Roles\UpdateRoleRequest;
use Modules\Identity\Http\Resources\RoleCollection;
use Modules\Identity\Http\Resources\RoleResource;
use Modules\Identity\Models\Role;
use Modules\Identity\Services\RoleQueryService;

class RoleController extends Controller
{
    public function index(IndexRolesRequest $request, RoleQueryService $query): JsonResponse
    {
        return ApiResponse::success(
            data: (new RoleCollection($query->paginate($request)))->resolve($request)
        );
    }

    public function show(Role $role, RoleQueryService $query): JsonResponse
    {
        return ApiResponse::success(
            data: (new RoleResource($query->find($role)))->resolve()
        );
    }

    public function store(StoreRoleRequest $request, CreateRoleAction $action): JsonResponse
    {
        return ApiResponse::success(
            data: (new RoleResource($action->execute($request->validated())))->resolve($request),
            status: HttpStatusCode::CREATED
        );
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRoleAction $action): JsonResponse
    {
        return ApiResponse::success(
            data: (new RoleResource($action->execute($role, $request->validated())))->resolve($request)
        );
    }

    public function destroy(Role $role, DeleteRoleAction $action): JsonResponse
    {
        $action->execute($role);
        return ApiResponse::success();
    }
}
