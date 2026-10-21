<?php

namespace Modules\Identity\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Identity\Actions\Users\CreateUserAction;
use Modules\Identity\Actions\Users\DeleteUserAction;
use Modules\Identity\Actions\Users\UpdateUserAction;
use Modules\Identity\Http\Requests\Users\IndexUsersRequest;
use Modules\Identity\Http\Requests\Users\StoreUserRequest;
use Modules\Identity\Http\Requests\Users\UpdateUserRequest;
use Modules\Identity\Http\Resources\UserCollection;
use Modules\Identity\Http\Resources\UserResource;
use Modules\Identity\Models\User;
use Modules\Identity\Services\UserQueryService;

class UserController extends Controller
{
    public function index(IndexUsersRequest $request, UserQueryService $users): JsonResponse
    {
        return ApiResponse::success(
            data: (new UserCollection($users->paginate($request)))->resolve($request),
        );
    }

    public function show(User $user, UserQueryService $users): JsonResponse
    {
        return ApiResponse::success(
            data: (new UserResource($users->find($user)))->resolve(),
        );
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $user = $action->execute($request->validated());

        return ApiResponse::success(
            data: (new UserResource($user))->resolve($request),
            status: HttpStatusCode::CREATED,
        );
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): JsonResponse
    {
        $user = $action->execute($user, $request->validated());

        return ApiResponse::success(
            data: (new UserResource($user))->resolve($request),
        );
    }

    public function destroy(User $user, DeleteUserAction $action): JsonResponse
    {
        $action->execute($user);

        return ApiResponse::success();
    }
}
