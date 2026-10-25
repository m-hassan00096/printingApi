<?php

namespace Modules\Settings\Tests\Feature\ParameterValues;

require_once __DIR__.'/Support/ParameterValueTestCase.php';

use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;

class SettingsArabicValidationTest extends ParameterValueTestCase
{
    public function test_required_field_errors_are_in_arabic_and_keyed_by_field(): void
    {
        app()->setLocale('ar');

        $this->postJson($this->url(), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'حقل الاسم مطلوب.')
            ->assertJsonPath('errors.parameterId.0', 'حقل مجموعة الإعدادات مطلوب.')
            ->assertJsonPath('errors.status.0', 'حقل الحالة مطلوب.');
    }

    public function test_duplicate_parameter_value_name_is_translated(): void
    {
        app()->setLocale('ar');
        $this->createValue($this->customerTypes, 'شركة');

        $this->postJson($this->url(), $this->payload([
            'parameterId' => $this->customerTypes->id,
            'name' => 'شركة',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'قيمة الاسم مستخدمة بالفعل.');
    }

    public function test_invalid_status_and_name_limit_are_translated(): void
    {
        app()->setLocale('ar');

        $this->postJson($this->url(), $this->payload([
            'name' => str_repeat('a', 151),
            'status' => 3,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'حقل الاسم يجب ألا يتجاوز 150 حرفًا.')
            ->assertJsonPath('errors.status.0', 'القيمة المحددة لحقل الحالة غير صالحة.');
    }
}
