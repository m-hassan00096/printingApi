<?php

namespace Modules\Settings\Tests\Feature\ParameterValues;

use InvalidArgumentException;
use Modules\Settings\Services\ParameterValueUsageRegistry;
use stdClass;
use Tests\TestCase;

class ParameterValueUsageRegistryTest extends TestCase
{
    public function test_invalid_guard_registration_uses_localized_messages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);

            try {
                app(ParameterValueUsageRegistry::class)->register(stdClass::class);
                $this->fail('Invalid guard registration should throw.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    __('settings.invalid_parameter_value_usage_guard', ['guard' => stdClass::class]),
                    $exception->getMessage()
                );
            }
        }
    }
}
