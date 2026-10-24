<?php
namespace Modules\Orders\Enums;

enum OrderProductionStatus: int
{
    case NOT_STARTED = 0;
    case IN_PRODUCTION = 1;
    case PARTIALLY_READY = 2;
    case READY_FOR_DELIVERY = 3;
    case DELIVERED = 4;

    public function label(): string
    {
        return __('orders.production.'.match ($this) {
            self::NOT_STARTED => 'not_started', self::IN_PRODUCTION => 'in_production',
            self::PARTIALLY_READY => 'partially_ready', self::READY_FOR_DELIVERY => 'ready_for_delivery',
            self::DELIVERED => 'delivered',
        });
    }
}
