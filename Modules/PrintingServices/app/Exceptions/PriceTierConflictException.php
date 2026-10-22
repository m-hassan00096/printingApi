<?php

namespace Modules\PrintingServices\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class PriceTierConflictException extends BusinessException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('printing_services.messages.price_tier_conflict'), HttpStatusCode::CONFLICT);
    }
}

