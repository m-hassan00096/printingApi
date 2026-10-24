<?php
namespace Modules\Orders\Enums;

enum OrderStatus: int
{
    case DRAFT = 0;
    case CONFIRMED = 1;
    case CANCELLED = 2;

    public function label(): string
    {
        return __('orders.status.'.match ($this) {
            self::DRAFT => 'draft', self::CONFIRMED => 'confirmed', self::CANCELLED => 'cancelled',
        });
    }
}
