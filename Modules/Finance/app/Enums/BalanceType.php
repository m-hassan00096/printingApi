<?php
namespace Modules\Finance\Enums;

enum BalanceType: int
{
    case ZERO = 0;
    case DEBIT = 1;
    case CREDIT = 2;

    public function label(): string
    {
        return __('finance.balance_types.'.match ($this) {
            self::ZERO => 'zero',
            self::DEBIT => 'debit',
            self::CREDIT => 'credit',
        });
    }
}
