<?php

namespace Modules\Settings\Tests\Feature\ParameterValues;

require_once __DIR__.'/Support/ParameterValueTestCase.php';

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Settings\Contracts\ParameterValueDeactivationGuard;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\ParameterValue;
use Modules\Settings\Services\ParameterValueUsageRegistry;
use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;
use stdClass;

class ParameterValueDeactivationTest extends ParameterValueTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A test-only in-memory guard avoids MySQL DDL implicit commits
        // inside the RefreshDatabase transaction (and lost SAVEPOINTs).
        FakeCurrentCustomerGuard::reset();

        // Simulates the future Customers module without creating its tables
        // or introducing a production dependency into Settings.
        app(ParameterValueUsageRegistry::class)
            ->registerDeactivationGuard(FakeCurrentCustomerGuard::class);
    }

    public function test_active_customer_type_in_use_cannot_be_deactivated_and_update_rolls_back(): void
    {
        $type = $this->createValue($this->customerTypes, 'Wholesale');
        FakeCurrentCustomerGuard::markInUse($type->id);

        $this->patchJson($this->url($type), [
            'name' => 'Renamed Wholesale',
            'status' => 0,
        ])->assertStatus(409)
            ->assertJsonPath('message', __('settings.customer_type_has_current_customers'));

        $this->assertSame(ParameterValueStatus::ACTIVE, $type->fresh()->status);
        $this->assertSame('Wholesale', $type->fresh()->name);
    }

    public function test_unreferenced_customer_type_can_be_deactivated(): void
    {
        $type = $this->createValue($this->customerTypes, 'Retail');

        $this->patchJson($this->url($type), ['status' => 0])
            ->assertOk()->assertJsonPath('data.status', 0);
    }

    public function test_other_settings_groups_are_unaffected(): void
    {
        $location = $this->createValue($this->printingLocations, 'Main Printer');
        FakeCurrentCustomerGuard::markInUse($location->id);

        $this->patchJson($this->url($location), ['status' => 0])
            ->assertOk()->assertJsonPath('data.status', 0);
    }

    public function test_renaming_does_not_trigger_deactivation_checks(): void
    {
        $type = $this->createValue($this->customerTypes, 'Wholesale');
        FakeCurrentCustomerGuard::markInUse($type->id);

        $this->patchJson($this->url($type), ['name' => 'Business'])
            ->assertOk()->assertJsonPath('data.name', 'Business');
    }

    public function test_reactivation_does_not_trigger_deactivation_checks(): void
    {
        $type = $this->createValue($this->customerTypes, 'Wholesale');
        $type->status = ParameterValueStatus::INACTIVE;
        $type->save();
        FakeCurrentCustomerGuard::markInUse($type->id);

        $this->patchJson($this->url($type), ['status' => 1])
            ->assertOk()->assertJsonPath('data.status', 1);
    }

    public function test_invalid_deactivation_guard_uses_translated_exception(): void
    {
        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);
            try {
                app(ParameterValueUsageRegistry::class)
                    ->registerDeactivationGuard(stdClass::class);

                $this->fail('Invalid guard registration should fail.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    __('settings.invalid_parameter_value_deactivation_guard', ['guard' => stdClass::class]),
                    $exception->getMessage(),
                );
            }
        }
    }
}

/**
 * Future Customers owns the real query. This class exists in tests only.
 */
class FakeCurrentCustomerGuard implements ParameterValueDeactivationGuard
{
    /** @var list<int> */
    private static array $inUse = [];

    public static function reset(): void
    {
        self::$inUse = [];
    }

    public static function markInUse(int $typeId): void
    {
        self::$inUse[] = $typeId;
    }

    public function assertCanDeactivate(ParameterValue $value): void
    {
        if ($value->parameter()->value('name') !== 'customer_types') {
            return;
        }

        if (in_array((int) $value->id, self::$inUse, true)) {
            throw new FakeCurrentCustomerInUseException();
        }
    }
}

class FakeCurrentCustomerInUseException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            __('settings.customer_type_has_current_customers'),
            HttpStatusCode::CONFLICT,
        );
    }
}

