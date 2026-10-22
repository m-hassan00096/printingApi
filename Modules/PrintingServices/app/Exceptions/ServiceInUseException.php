<?php

namespace Modules\PrintingServices\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class ServiceInUseException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('A service already used by an order cannot be deleted.', HttpStatusCode::CONFLICT);
    }
}

