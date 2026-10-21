<?php

namespace Modules\Identity\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Identity\Actions\Auth\LogoutAction;

class LogoutController extends Controller
{
    public function __invoke(
        Request $request,
        LogoutAction $action
    ): JsonResponse {
        $action->execute(
            session: $request->session(),
        );

        return ApiResponse::success();
    }
}
