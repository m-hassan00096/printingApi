<?php
namespace Modules\Deliveries\Enums;

enum DeliveryStatus: int
{
    case ACTIVE = 1;
    case CANCELLED = 2;

    public function label(): string
    {
        return __('deliveries.status.'.($this === self::ACTIVE ? 'active' : 'cancelled'));
    }
}
