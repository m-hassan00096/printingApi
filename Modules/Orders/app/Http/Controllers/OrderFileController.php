<?php
namespace Modules\Orders\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Orders\Http\Requests\UploadOrderFileRequest;
use Modules\Orders\Http\Resources\OrderItemFileResource;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Orders\Models\OrderItemFile;
use Modules\Orders\Services\OrderFileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderFileController extends Controller
{
    public function store(
        UploadOrderFileRequest $request,
        Order $order,
        OrderItem $item,
        OrderFileService $files,
    ): JsonResponse {
        $file = $files->upload($order, $item, $request->file('file'));

        return ApiResponse::success(
            data: (new OrderItemFileResource($file))->resolve($request),
            message: __('orders.messages.file_uploaded'),
            status: HttpStatusCode::CREATED,
        );
    }

    public function download(
        Order $order,
        OrderItem $item,
        OrderItemFile $file,
        OrderFileService $files,
    ): StreamedResponse {
        return $files->download($order, $item, $file);
    }

    public function destroy(Order $order, OrderItem $item, OrderItemFile $file, OrderFileService $files): JsonResponse
    {
        $files->delete($order, $item, $file);

        return ApiResponse::success(message: __('orders.messages.file_deleted'));
    }
}
