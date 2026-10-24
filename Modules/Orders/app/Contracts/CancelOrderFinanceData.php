<?php
namespace Modules\Orders\Contracts;

final readonly class CancelOrderFinanceData
{
    public function __construct(
        public int $orderId,
        public int $customerId,
        public string $totalAmount,
        public string $idempotencyKey,
        public ?int $actorId = null,
    ) {}
}
