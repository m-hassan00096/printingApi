<?php
namespace Modules\Customers\Tests\Feature;

require_once __DIR__.'/Support/CustomerTestCase.php';

use Modules\Customers\Tests\Feature\Support\CustomerTestCase;

class CustomerLocalizationTest extends CustomerTestCase
{
    public function test_create_update_success_messages_use_arabic_and_english_translations(): void
    {
        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);
            $phone = $locale === 'ar' ? '01000000001' : '01000000002';

            $this->postJson('/api/v1/customers', $this->payload([
                'phone' => $phone,
            ]))->assertCreated()
                ->assertJsonPath('message', __('customers.messages.created'))
                ->assertJsonPath('data', null);

            $customer = \Modules\Customers\Models\Customer::query()->where('phone', $phone)->firstOrFail();
            $this->patchJson($this->url($customer), ['name' => 'Renamed'])
                ->assertOk()
                ->assertJsonPath('message', __('customers.messages.updated'))
                ->assertJsonPath('data', null);

            $this->postJson('/api/v1/customers', $this->payload([
                'phone' => $phone,
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors('phone');
        }
    }
}
