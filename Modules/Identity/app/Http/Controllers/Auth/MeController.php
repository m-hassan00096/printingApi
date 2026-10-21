<?php

namespace Modules\Identity\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Identity\Http\Resources\LoggedInUserResource;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->loadMissing('roles.translations');

        return ApiResponse::success(
            data: (new LoggedInUserResource($user))
                ->resolve($request),
        );
    }
}
