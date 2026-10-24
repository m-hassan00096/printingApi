<?php
namespace Modules\Finance\Enums;

enum CollectionSource: int
{
    case GENERAL = 0;
    case ORDER_DEPOSIT = 1;
    case DELIVERY = 2;

    public function label(): string
    {
        return __('finance.collection_sources.'.match ($this) {
            self::GENERAL => 'general',
            self::ORDER_DEPOSIT => 'order_deposit',
            self::DELIVERY => 'delivery',
        });
    }
}
