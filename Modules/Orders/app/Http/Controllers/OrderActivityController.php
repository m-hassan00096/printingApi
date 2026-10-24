<?php
namespace Modules\Orders\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Orders\Http\Resources\OrderActivityResource;
use Modules\Orders\Models\Order;

class OrderActivityController extends Controller
{
    public function __invoke(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $activities = $order->activities()->orderByDesc('id')
            ->paginate((int) ($validated['perPage'] ?? 15));

        return ApiResponse::success(data: [
            'activities' => OrderActivityResource::collection($activities->getCollection())->resolve($request),
            'pagination' => [
                'currentPage' => $activities->currentPage(),
                'lastPage' => $activities->lastPage(),
                'perPage' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }
}
