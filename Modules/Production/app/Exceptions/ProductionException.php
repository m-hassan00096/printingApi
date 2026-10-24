<?php
namespace Modules\Production\Exceptions;

use App\Exceptions\BusinessException;

class ProductionException extends BusinessException
{
    public function __construct(string $key)
    {
        parent::__construct(__('production.messages.'.$key), \App\Enums\HttpStatusCode::CONFLICT);
    }
}
