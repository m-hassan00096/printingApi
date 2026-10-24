<?php
namespace Modules\Settings\Contracts;

interface PrintingLocationLookup
{
    /** Only active, non-deleted entries in printing_locations. */
    public function available(int $id): bool;

    /** Lock valid choice in the writer transaction; return null if deactivated/deleted. */
    public function lockActive(int $id): ?string;

    /** Human-readable name, including inactive and soft-deleted historic values. */
    public function name(int $id): ?string;
}
