<?php
namespace Modules\Finance\Enums;

enum PaymentMethod: int
{
    case CASH = 0;
    case BANK_TRANSFER = 1;
    case E_WALLET = 2;

    public function label(): string
    {
        return __('finance.payment_methods.'.match ($this) {
            self::CASH => 'cash',
            self::BANK_TRANSFER => 'bank_transfer',
            self::E_WALLET => 'e_wallet',
        });
    }
}
