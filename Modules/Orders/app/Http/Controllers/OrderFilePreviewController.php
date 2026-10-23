<?php

namespace Modules\Orders\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Orders\Models\OrderItemFile;
use Modules\Orders\Services\OrderFileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderFilePreviewController extends Controller
{
    public function __invoke(OrderItemFile $file, OrderFileService $files): StreamedResponse
    {
        // The "signed" middleware validates expiry and tampering before access.
        // Parent lifecycle and private storage checks live in the service.
        return $files->preview($file);
    }
}
