<?php
namespace Modules\Finance\Exceptions;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class FinanceException extends BusinessException
{
    public function __construct(string $key, HttpStatusCode $status = HttpStatusCode::CONFLICT)
    {
        parent::__construct(__('finance.messages.'.$key), $status);
    }
}
