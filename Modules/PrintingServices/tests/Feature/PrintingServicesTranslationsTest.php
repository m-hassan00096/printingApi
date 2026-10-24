<?php

namespace Modules\PrintingServices\Tests\Feature;

require_once __DIR__.'/Support/PrintingServiceTestCase.php';
require_once __DIR__.'/Support/FakeOrderServiceUsageGuard.php';

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PrintingServices\Tests\Feature\Support\PrintingServiceTestCase;
use Modules\PrintingServices\Tests\Feature\Support\FakeOrderServiceUsageGuard;
use Modules\PrintingServices\Services\PrintingServiceUsageRegistry;

class PrintingServicesTranslationsTest extends PrintingServiceTestCase
{
    public function test_arabic_standard_validation_messages_are_translated(): void
    {
        app()->setLocale('ar');

        $this->postJson('/api/v1/printing-services', [
            'status' => 999,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'حقل اسم خدمة الطباعة مطلوب.')
            ->assertJsonPath('errors.status.0', 'القيمة المحددة لحقل الحالة غير صالحة.');
    }

    public function test_arabic_base_and_tier_prices_use_translation_keys(): void
    {
        app()->setLocale('ar');

        $this->postJson('/api/v1/printing-services', $this->payload([
            'basePrice' => '-2.00',
        ]))->assertUnprocessable()
            ->assertJsonPath(
                'errors.basePrice.0',
                'يجب أن يكون السعر الأساسي صفرًا أو أكبر، وبحد أقصى منزلتين عشريتين، وبالجنيه المصري.',
            );

        $response = $this->postJson('/api/v1/printing-services', $this->payload([
            'priceTiers' => [['price' => '0.00']],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('priceTiers.0.price');

        // Laravel's validation errors use flat keys containing dots, not nested JSON objects.
        $this->assertSame(
            'يجب أن يكون سعر الشريحة أكبر من صفر، وبحد أقصى منزلتين عشريتين، وبالجنيه المصري.',
            $response->json('errors')['priceTiers.0.price'][0],
        );
    }

    public function test_english_validation_uses_english_catalog(): void
    {
        app()->setLocale('en');

        $this->postJson('/api/v1/printing-services', $this->payload([
            'basePrice' => '-2.00',
        ]))->assertUnprocessable()
            ->assertJsonPath(
                'errors.basePrice.0',
                'The base price must be zero or greater, in EGP, with at most two decimal places.',
            );

        $response = $this->postJson('/api/v1/printing-services', $this->payload([
            'priceTiers' => [['price' => '0.00']],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('priceTiers.0.price');

        $this->assertSame(
            'The tier price must be greater than zero, in EGP, with at most two decimal places.',
            $response->json('errors')['priceTiers.0.price'][0],
        );
    }

    public function test_arabic_nested_tier_customer_type_message_uses_row_path(): void
    {
        app()->setLocale('ar');
        $service = $this->service();
        $tierId = $this->tier($service);

        $response = $this->patchJson('/api/v1/printing-services/'.$service->id, [
            'priceTiers' => [[
                'actionStatus' => 2,
                'priceTierId' => $tierId,
                'customerTypeId' => 999999,
                'minQuantity' => 100,
                'maxQuantity' => 500,
                'price' => '5.00',
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('priceTiers.0.customerTypeId');

        $this->assertSame(
            'نوع العميل غير موجود أو غير نشط ضمن أنواع العملاء في الإعدادات.',
            $response->json('errors')['priceTiers.0.customerTypeId'][0],
        );
    }

    public function test_arabic_quantity_overlap_uses_translated_business_message(): void
    {
        app()->setLocale('ar');

        $this->postJson('/api/v1/printing-services', $this->payload([
            'priceTiers' => [
                ['minQuantity' => 100, 'maxQuantity' => 200, 'price' => '5.00'],
                ['minQuantity' => 150, 'maxQuantity' => 300, 'price' => '6.00'],
            ],
        ]))->assertUnprocessable()
            ->assertJsonPath(
                'errors.priceTiers.0',
                'لا يُسمح بتداخل نطاقات الكميات لنفس نوع العميل.',
            );
    }

    public function test_arabic_inactive_service_and_service_in_use_business_errors(): void
    {
        app()->setLocale('ar');
        $service = $this->service();
        $this->patchJson('/api/v1/printing-services/'.$service->id, [
            'status' => 0,
        ])->assertOk();

        $this->postJson('/api/v1/printing-services/price-preview', [
            'serviceId' => $service->id,
            'customerTypeId' => $this->wholesale->id,
            'quantity' => 100,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.serviceId.0',
                'خدمة الطباعة غير نشطة ولا يمكن استخدامها في طلب جديد.',
            );

        $this->historicalOrderItem($service);

        $this->deleteJson('/api/v1/printing-services/'.$service->id)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                __('orders.messages.service_in_use'),
            );
    }
}
