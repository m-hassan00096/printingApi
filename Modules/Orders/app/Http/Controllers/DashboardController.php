<?php
namespace Modules\Orders\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Orders\Services\DashboardQueryService;

class DashboardController extends Controller
{
    public function __invoke(DashboardQueryService $dashboard): JsonResponse
    {
        return ApiResponse::success(data: $dashboard->overview());
    }
}
