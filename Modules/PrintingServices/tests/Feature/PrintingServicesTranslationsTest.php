<?php

namespace Modules\PrintingServices\Tests\Feature;

require_once __DIR__.'/Support/PrintingServiceTestCase.php';

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PrintingServices\Tests\Feature\Support\PrintingServiceTestCase;

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

        $this->postJson('/api/v1/printing-services', $this->payload([
            'priceTiers' => [['price' => '0.00']],
        ]))->assertUnprocessable()
            ->assertJsonPath(
                'errors.priceTiers.0.price.0',
                'يجب أن يكون سعر الشريحة أكبر من صفر، وبحد أقصى منزلتين عشريتين، وبالجنيه المصري.',
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

        $this->postJson('/api/v1/printing-services', $this->payload([
            'priceTiers' => [['price' => '0.00']],
        ]))->assertUnprocessable()
            ->assertJsonPath(
                'errors.priceTiers.0.price.0',
                'The tier price must be greater than zero, in EGP, with at most two decimal places.',
            );
    }

    public function test_arabic_nested_tier_customer_type_message_uses_row_path(): void
    {
        app()->setLocale('ar');
        $service = $this->service();
        $tierId = $this->tier($service);

        $this->patchJson('/api/v1/printing-services/'.$service->id, [
            'priceTiers' => [[
                'actionStatus' => 2,
                'priceTierId' => $tierId,
                'customerTypeId' => 999999,
                'minQuantity' => 100,
                'maxQuantity' => 500,
                'price' => '5.00',
            ]],
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.priceTiers.0.customerTypeId.0',
                'نوع العميل غير موجود أو غير نشط ضمن أنواع العملاء في الإعدادات.',
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

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('printing_service_id')->constrained('printing_services');
        });
        DB::table('order_items')->insert(['printing_service_id' => $service->id]);

        $this->deleteJson('/api/v1/printing-services/'.$service->id)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'لا يمكن حذف خدمة الطباعة لأنها مستخدمة في طلب.',
            );
    }
}
