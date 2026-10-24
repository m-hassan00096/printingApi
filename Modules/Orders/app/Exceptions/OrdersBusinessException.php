<?php
namespace Modules\Orders\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class OrdersBusinessException extends BusinessException
{
    public function __construct(string $key, HttpStatusCode $status = HttpStatusCode::CONFLICT)
    {
        parent::__construct(__('orders.messages.'.$key), $status);
    }
}
