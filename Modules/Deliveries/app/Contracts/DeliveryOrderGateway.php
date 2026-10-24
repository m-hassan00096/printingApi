<?php
namespace Modules\Deliveries\Contracts;

interface DeliveryOrderGateway
{
    /** @return array{orders: array, pagination: array} */
    public function ready(array $filters, int $page, int $perPage, string $sort): array;
    public function preview(int $orderId): ?array;
    /** Lock order then every active item; returns {orderId,customerId} or throws 409. */
    public function lockEligible(int $orderId): array;
    public function markDelivered(int $orderId, \DateTimeInterface $instant, string $localDate): void;
    /** Locks a delivered order and returns customerId; caller then locks delivery. */
    public function lockDelivered(int $orderId): array;
    public function restoreReady(int $orderId): void;
    /** @return array<int,array> */
    public function snapshots(array $orderIds): array;
}
