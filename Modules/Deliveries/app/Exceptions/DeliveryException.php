<?php
namespace Modules\Deliveries\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class DeliveryException extends BusinessException
{
    public function __construct(string $key)
    {
        parent::__construct(__('deliveries.messages.'.$key), HttpStatusCode::CONFLICT);
    }
}
