<?php

namespace Modules\PrintingServices\Http\Requests\Concerns;

use Closure;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Support\ServiceName;

trait ValidatesServiceName
{
    protected function prepareServiceName(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => ServiceName::normalize($this->input('name'))]);
        }
    }

    protected function uniqueLiveName(?int $ignoreId = null): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $query = PrintingService::query()
                ->where('live_name_hash', ServiceName::hash($value));
            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }
            if ($query->exists()) {
                $fail('The service name is already in use.');
            }
        };
    }
}

