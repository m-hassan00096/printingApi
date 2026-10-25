<?php

namespace Modules\Settings\Tests\Feature\Selects;

require_once __DIR__.'/../ParameterValues/Support/ParameterValueTestCase.php';

use App\Exceptions\Select\InvalidSelectRequestException;
use App\Services\Select\SelectRegistry;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Services\Selects\ParameterValueSelectService;
use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;

class SettingsSelectServicesTest extends ParameterValueTestCase
{
    public function test_settings_module_registers_customer_types_with_scoped_access(): void
    {
        $registry = app(SelectRegistry::class);

        $this->assertSame([
            ParameterValueSelectService::class,
            'getPrintingLocations',
            'settings.selects.view',
        ], $registry->resolve('printingLocations'));

        $this->assertSame([
            ParameterValueSelectService::class,
            'getCustomerTypes',
            'selects.customerTypes',
        ], $registry->resolve('customerTypes'));
    }

    public function test_printing_locations_return_active_value_and_label_only(): void
    {
        $visible = $this->createValue($this->printingLocations, 'Print House A');
        $this->createValue($this->printingLocations, 'Inactive House', [
            'status' => ParameterValueStatus::INACTIVE,
        ]);
        $deleted = $this->createValue($this->printingLocations, 'Deleted House');
        $deleted->delete();
        $this->createValue($this->customerTypes, 'Company');

        $this->assertSame([
            ['value' => $visible->id, 'label' => 'Print House A'],
        ], app(ParameterValueSelectService::class)->getPrintingLocations());
    }

    public function test_customer_types_do_not_include_printing_locations(): void
    {
        $visible = $this->createValue($this->customerTypes, 'Company');
        $this->createValue($this->printingLocations, 'Location');

        $this->assertSame([
            ['value' => $visible->id, 'label' => 'Company'],
        ], app(ParameterValueSelectService::class)->getCustomerTypes());
    }

    public function test_customer_types_exclude_inactive_and_soft_deleted_values(): void
    {
        $visible = $this->createValue($this->customerTypes, 'Business');
        $this->createValue($this->customerTypes, 'Individual', [
            'status' => ParameterValueStatus::INACTIVE,
        ]);
        $deleted = $this->createValue($this->customerTypes, 'Archived');
        $deleted->delete();

        $this->assertSame([
            ['value' => $visible->id, 'label' => 'Business'],
        ], app(ParameterValueSelectService::class)->getCustomerTypes());
    }

    public function test_options_are_sorted_by_name_then_id(): void
    {
        $zulu = $this->createValue($this->printingLocations, 'Zulu');
        $alpha = $this->createValue($this->printingLocations, 'Alpha');

        $this->assertSame([
            ['value' => $alpha->id, 'label' => 'Alpha'],
            ['value' => $zulu->id, 'label' => 'Zulu'],
        ], app(ParameterValueSelectService::class)->getPrintingLocations());
    }

    public function test_no_active_values_returns_empty_options(): void
    {
        $this->createValue($this->printingLocations, 'Inactive', [
            'status' => ParameterValueStatus::INACTIVE,
        ]);

        $this->assertSame([], app(ParameterValueSelectService::class)->getPrintingLocations());
        $this->assertSame([], app(ParameterValueSelectService::class)->getCustomerTypes());
    }

    public function test_explicit_active_filter_is_supported(): void
    {
        $visible = $this->createValue($this->customerTypes, 'Company');

        $this->assertSame([
            ['value' => $visible->id, 'label' => 'Company'],
        ], app(ParameterValueSelectService::class)->getCustomerTypes('active'));
    }

    public function test_printing_locations_reject_unsupported_filter(): void
    {
        $this->expectException(InvalidSelectRequestException::class);

        app(ParameterValueSelectService::class)->getPrintingLocations('all');
    }

    public function test_customer_types_reject_unsupported_filter(): void
    {
        $this->expectException(InvalidSelectRequestException::class);

        app(ParameterValueSelectService::class)->getCustomerTypes('inactive');
    }
}
