<?php

namespace Modules\Settings\Enums;

/**
 * Shared default flag for bank accounts and e-wallets.
 * Persisted and exposed as 0/1, never a boolean.
 */
enum DefaultStatus: int
{
    case NOT_DEFAULT = 0;
    case DEFAULT = 1;
}
