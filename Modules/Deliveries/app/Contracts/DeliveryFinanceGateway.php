<?php
namespace Modules\Deliveries\Contracts;

interface DeliveryFinanceGateway
{
    /** @param list<int> $customerIds @return array<int,array> keyed by customer */
    public function balances(array $customerIds): array;
    /** Collection ID returned; called inside outer transaction after Orders lock. */
    public function post(int $orderId, int $customerId, string $amount, int $method, ?int $destinationId, int $actorId, bool $confirmCredit): int;
    /** Only the exact linked delivery collection may be reversed; returns original and effective money data. */
    public function reverse(int $collectionId, int $orderId, int $customerId, int $actorId, string $reason, bool $fundsConfirmed): void;
    /** @return array<int,array> keyed by collection ID */
    public function collections(array $collectionIds): array;
}
