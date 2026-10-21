<?php

namespace App\Services\Select;

use InvalidArgumentException;

class SelectRegistry
{
    private array $selects = [];

    public function register(array $selects): void
    {
        foreach ($selects as $key => $definition) {
            if (isset($this->selects[$key])) {
                throw new InvalidArgumentException(
                    "Select [{$key}] is already registered."
                );
            }

            $this->selects[$key] = $definition;
        }
    }

    public function resolve(string $key): ?array
    {
        return $this->selects[$key] ?? null;
    }

    public function all(): array
    {
        return $this->selects;
    }
}
