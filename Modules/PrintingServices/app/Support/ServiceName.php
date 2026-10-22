<?php

namespace Modules\PrintingServices\Support;

final class ServiceName
{
    public static function normalize(string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($name));

        return trim($normalized ?? $name);
    }

    public static function hash(string $normalized): string
    {
        return hash('sha256', $normalized);
    }
}

