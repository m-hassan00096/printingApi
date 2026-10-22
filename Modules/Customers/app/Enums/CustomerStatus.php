<?php
namespace Modules\Customers\Enums;

enum CustomerStatus: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;

    public function label(): string
    {
        return __('customers.status.'.($this === self::ACTIVE ? 'active' : 'inactive'));
    }
}
