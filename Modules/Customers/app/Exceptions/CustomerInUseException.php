<?php
namespace Modules\Customers\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class CustomerInUseException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(__('customers.messages.customer_in_use'), HttpStatusCode::CONFLICT);
    }
}
