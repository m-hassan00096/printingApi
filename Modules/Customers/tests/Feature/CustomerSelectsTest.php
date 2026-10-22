<?php
namespace Modules\Customers\Tests\Feature;

require_once __DIR__.'/Support/CustomerTestCase.php';

use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Tests\Feature\Support\CustomerTestCase;

class CustomerSelectsTest extends CustomerTestCase
{
    public function test_both_selects_include_phone_and_have_distinct_status_scopes(): void
    {
        $live = $this->customer(['name' => 'Ahmed', 'phone' => '01012345678']);
        $this->customer(['name' => 'Mona', 'phone' => '01111111111', 'status' => CustomerStatus::INACTIVE]);
        $deleted = $this->customer(['name' => 'Deleted', 'phone' => '01222222222']);
        $deleted->delete();

        $this->getJson('/api/v1/selects?selects=customers,activeCustomers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'customers')
            ->assertJsonCount(2, 'data.0.options')
            ->assertJsonPath('data.0.options.0.label', 'Ahmed - 01012345678')
            ->assertJsonPath('data.1.label', 'activeCustomers')
            ->assertJsonCount(1, 'data.1.options')
            ->assertJsonPath('data.1.options.0.value', $live->id);
    }

    public function test_unsupported_select_parameter_is_rejected(): void
    {
        $this->getJson('/api/v1/selects?selects=customers&params[customers]=inactive')
            ->assertUnprocessable();

        $this->getJson('/api/v1/selects?selects=activeCustomers&params[activeCustomers]=active')
            ->assertOk();
    }
}
