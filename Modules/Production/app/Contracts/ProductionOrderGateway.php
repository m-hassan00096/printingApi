<?php
namespace Modules\Production\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface ProductionOrderGateway
{
    /**
     * Called inside the Production transaction after optional location validation.
     * Locks affected orders then items in ascending id order, changes state and updates rollups.
     * @return list<array{orderItemId: int, event: string, fromStage: int, toStage: int, oldLocationId: ?int, newLocationId: ?int}>
     */
    public function transition(array $itemIds, string $action, int $actorId, ?int $printingLocationId): array;

    /** @return array{items: list<array>, pagination: array} */
    public function listItems(array $filters, int $page, int $perPage, string $sort, ?Builder $myItemQuery): array;

    /** @return array */
    public function summary(array $filters, ?Builder $myItemQuery): array;

    /** Read-only and production-scoped item details (specifications and file metadata, never prices). */
    public function itemDetails(int $itemId): ?array;

    /** Whether item is still a visible, confirmed item, also before showing history. */
    public function itemExists(int $itemId): bool;
}
