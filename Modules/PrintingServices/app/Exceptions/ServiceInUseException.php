<?php

namespace Modules\PrintingServices\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class ServiceInUseException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(__('printing_services.messages.service_in_use'), HttpStatusCode::CONFLICT);
    }
}

