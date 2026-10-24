<?php
namespace Modules\Settings\Contracts;

interface PrintingLocationLookup
{
    /** Group membership, regardless of active/soft-deleted state (input reference validation). */
    public function belongsToPrintingLocations(int $id): bool;

    /** Only active, non-deleted entries in printing_locations. */
    public function available(int $id): bool;

    /** Lock valid choice in the writer transaction; return null if deactivated/deleted. */
    public function lockActive(int $id): ?string;

    /** Human-readable name, including inactive and soft-deleted historic values. */
    public function name(int $id): ?string;

    /** @param list<int> $ids @return array<int, string|null> */
    public function names(array $ids): array;
}
