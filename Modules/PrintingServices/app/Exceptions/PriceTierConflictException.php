<?php

namespace Modules\PrintingServices\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class PriceTierConflictException extends BusinessException
{
    public function __construct(string $message = 'Conflicting printing service price tiers.')
    {
        parent::__construct($message, HttpStatusCode::CONFLICT);
    }
}

