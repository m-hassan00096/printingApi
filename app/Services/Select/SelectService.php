<?php

namespace App\Services\Select;

use App\Exceptions\Select\InvalidSelectRequestException;
use App\Exceptions\Select\UnknownSelectException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Gate;
use LogicException;

class SelectService
{
    private const MAX_SELECTS = 20;

    public function __construct(
        private readonly SelectRegistry $registry,
    ) {}

    /**
     * Return a collection of {label, options} in the same order as requested.
     *
     * Each registry definition is [ProviderClass::class, 'method', ?'permission.key'].
     * Only registered provider methods are callable; the caller never supplies
     * a PHP class name or method.
     *
     * @param array<string, string> $parameters
     * @return array<int, array{label: string, options: array}>
     */
    public function getSelects(string $selects, array $parameters = []): array
    {
        // Commas are the recommended delimiter. URL-encoded '#' remains supported.
        $keys = array_map('trim', preg_split('/[,#]/', $selects) ?: []);

        if (
            $keys === []
            || count($keys) > self::MAX_SELECTS
            || count($keys) !== count(array_unique($keys))
        ) {
            throw new InvalidSelectRequestException();
        }

        foreach ($keys as $key) {
            if (! preg_match('/\A[a-zA-Z][a-zA-Z0-9_]*\z/D', $key)) {
                throw new InvalidSelectRequestException();
            }
        }

        // Prevent sending parameters to keys that were not requested.
        foreach ($parameters as $key => $value) {
            if (! is_string($key) || ! in_array($key, $keys, true) || ! is_string($value)) {
                throw new InvalidSelectRequestException();
            }
        }

        $result = [];

        foreach ($keys as $key) {
            $definition = $this->registry->resolve($key);

            if ($definition === null) {
                throw new UnknownSelectException($key);
            }

            // Invalid registrations are programmer errors, not user errors.
            if (
                ! is_array($definition)
                || ! isset($definition[0], $definition[1])
                || ! is_string($definition[0])
                || ! is_string($definition[1])
            ) {
                throw new LogicException("Invalid registration for select [{$key}].");
            }

            [$providerClass, $method] = $definition;
            $permission = $definition[2] ?? null;

            if ($permission !== null) {
                if (! is_string($permission) || $permission === '') {
                    throw new LogicException("Invalid permission for select [{$key}].");
                }

                Gate::authorize($permission);
            }

            // Container resolution supports dependency injection in module services.
            $provider = app($providerClass);

            if (! is_callable([$provider, $method])) {
                throw new LogicException("Invalid provider method for select [{$key}].");
            }

            $options = array_key_exists($key, $parameters)
                ? $provider->{$method}($parameters[$key])
                : $provider->{$method}();

            if ($options instanceof Arrayable) {
                $options = $options->toArray();
            }

            if (! is_array($options)) {
                throw new LogicException("Select [{$key}] must return an array or Arrayable.");
            }

            $result[] = [
                'label' => $key,
                'options' => $options,
            ];
        }

        return $result;
    }
}
