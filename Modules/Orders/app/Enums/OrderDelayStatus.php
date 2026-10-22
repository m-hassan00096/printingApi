<?php
namespace Modules\Orders\Enums;

enum OrderDelayStatus: int
{
    case NO_DUE_DATE = 0;
    case LATE = 1;
    case ON_TIME = 2;

    public function label(): string
    {
        return __('orders.delay.'.match ($this) {
            self::NO_DUE_DATE => 'no_due_date', self::LATE => 'late', self::ON_TIME => 'on_time',
        });
    }
}
