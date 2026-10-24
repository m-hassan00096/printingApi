<?php
namespace Modules\Finance\Services;

use Modules\Finance\Exceptions\FinanceException;
use Modules\Settings\Contracts\PaymentDestinationLookup;

class PaymentDetailsService
{
    public function __construct(private readonly PaymentDestinationLookup $destinations) {}

    /** @return array{bankAccountId: ?int, eWalletId: ?int, label: ?string} */
    public function resolve(int $paymentMethod, ?int $paymentDestinationId): array
    {
        return $this->destinations->resolve($paymentMethod, $paymentDestinationId)
            ?? throw new FinanceException('invalid_destination');
    }
}
