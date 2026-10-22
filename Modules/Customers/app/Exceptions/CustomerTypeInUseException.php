<?php
namespace Modules\Customers\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class CustomerTypeInUseException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(__('customers.messages.customer_type_in_use'), HttpStatusCode::CONFLICT);
    }
}
