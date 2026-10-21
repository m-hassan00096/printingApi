<?php

namespace Modules\Identity\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Identity\Actions\Auth\LoginAction;
use Modules\Identity\Http\Requests\LoginRequest;
use Modules\Identity\Http\Resources\LoggedInUserResource;

class LoginController extends Controller
{
    public function __invoke(
        LoginRequest $request,
        LoginAction $action
    ): JsonResponse {
        $user = $action->execute(
            credentials: $request->validated(),
            session: $request->session(),
        );

        $user->loadMissing('roles.translations');

        return ApiResponse::success(
            data: (new LoggedInUserResource($user))
                ->resolve($request),
        );
    }
}
