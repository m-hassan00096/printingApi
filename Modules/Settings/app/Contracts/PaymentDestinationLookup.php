<?php
namespace Modules\Settings\Contracts;

/** Settings owns destination lookup/validation; consumers never query its models directly. */
interface PaymentDestinationLookup
{
    public function available(int $method, ?int $destinationId): bool;

    /** Returns null if the selected destination was disabled/deleted since HTTP validation.
     * @return array{bankAccountId: ?int, eWalletId: ?int, label: ?string}|null
     */
    public function resolve(int $method, ?int $destinationId): ?array;
}
