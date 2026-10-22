<?php

namespace Modules\PrintingServices\Tests\Feature;

use InvalidArgumentException;
use Modules\PrintingServices\Services\PrintingServiceUsageRegistry;
use stdClass;
use Tests\TestCase;

class PrintingServiceUsageRegistryTest extends TestCase
{
    public function test_rejects_an_invalid_usage_guard_with_translated_message(): void
    {
        $registry = app(PrintingServiceUsageRegistry::class);

        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);

            try {
                $registry->register(stdClass::class);
                $this->fail('Invalid guard registration should fail.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    __('printing_services.messages.invalid_service_usage_guard', [
                        'guard' => stdClass::class,
                    ]),
                    $exception->getMessage(),
                );
            }
        }
    }
}

