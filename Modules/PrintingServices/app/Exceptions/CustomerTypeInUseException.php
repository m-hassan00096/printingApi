<?php

namespace Modules\PrintingServices\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class CustomerTypeInUseException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            message: __('printing_services.messages.customer_type_in_use'),
            status: HttpStatusCode::CONFLICT,
        );
    }
}

