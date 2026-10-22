<?php
namespace Modules\Customers\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class CustomerHasUndeliveredOrdersException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(__('customers.messages.undelivered_orders'), HttpStatusCode::CONFLICT);
    }
}
