<?php
namespace Modules\Orders\Contracts;

/** Transport-only DTO; never pass Orders' Eloquent models into Finance. */
final readonly class ConfirmOrderFinanceData
{
    public function __construct(
        public int $orderId,
        public int $customerId,
        public string $totalAmount,
        public string $idempotencyKey,
        public ?string $depositAmount = null,
        public ?int $paymentMethod = null,
        public ?int $paymentDestinationId = null,
        public ?int $actorId = null,
    ) {}
}
