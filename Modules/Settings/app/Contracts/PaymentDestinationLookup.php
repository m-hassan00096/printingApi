<?php
namespace Modules\Settings\Contracts;

/** Stable contract: consumer supplies numeric method, implementation owns bank/wallet tables. */
interface PaymentDestinationLookup
{
    /** @return array{bankAccountId: ?int, eWalletId: ?int, label: ?string} */
    public function resolve(int $method, ?int $destinationId): array;
}
