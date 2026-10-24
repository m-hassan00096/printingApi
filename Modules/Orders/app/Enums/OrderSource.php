<?php
namespace Modules\Orders\Enums;

enum OrderSource: int
{
    case BRANCH = 0;
    case ONLINE = 1;

    public function label(): string
    {
        return __('orders.source.'.($this === self::BRANCH ? 'branch' : 'online'));
    }
}
