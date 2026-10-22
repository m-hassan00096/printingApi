<?php

namespace Modules\PrintingServices\Tests\Feature;

require_once __DIR__.'/Support/PrintingServiceTestCase.php';

use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Tests\Feature\Support\PrintingServiceTestCase;

class PrintingServicesCrudTest extends PrintingServiceTestCase
{
    public function test_creates_service_and_initial_tiers_atomically(): void
    {
        $this->postJson('/api/v1/printing-services', $this->payload([
            'name' => '   Business   Cards   ',
            'priceTiers' => [
                ['customerTypeId' => null, 'minQuantity' => 100, 'maxQuantity' => 500, 'price' => '7.50'],
                ['customerTypeId' => $this->wholesale->id, 'minQuantity' => null, 'maxQuantity' => null, 'price' => '6.00'],
            ],
        ]))->assertCreated()
            ->assertJsonPath('data.name', 'Business Cards')
            ->assertJsonPath('data.status', 1)
            ->assertJsonCount(2, 'data.priceTiers');

        $this->assertDatabaseCount('printing_services', 1);
        $this->assertDatabaseCount('printing_service_price_tiers', 2);
    }

    public function test_service_names_are_case_sensitive_and_normalized(): void
    {
        $this->postJson('/api/v1/printing-services', $this->payload())->assertCreated();
        $this->postJson('/api/v1/printing-services', $this->payload(['name' => '  Business    Cards  ']))
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/printing-services', $this->payload(['name' => 'business cards']))
            ->assertCreated();
        $this->assertDatabaseCount('printing_services', 2);
    }

    public function test_base_price_may_be_null_or_zero_but_tier_price_must_be_positive(): void
    {
        $this->postJson('/api/v1/printing-services', $this->payload(['basePrice' => null]))
            ->assertCreated()->assertJsonPath('data.basePrice', null);
        $this->postJson('/api/v1/printing-services', $this->payload(['name' => 'Free Service', 'basePrice' => '0.00']))
            ->assertCreated()->assertJsonPath('data.basePrice', '0.00');
        $this->postJson('/api/v1/printing-services', $this->payload([
            'name' => 'Invalid Service', 'priceTiers' => [['price' => '0.00']],
        ]))->assertUnprocessable()->assertJsonValidationErrors('priceTiers.0.price');
        $this->assertDatabaseCount('printing_services', 2);
    }

    public function test_rejects_overlapping_ranges_but_allows_one_catch_all(): void
    {
        $this->postJson('/api/v1/printing-services', $this->payload([
            'priceTiers' => [
                ['minQuantity' => 1, 'maxQuantity' => 200, 'price' => '5.00'],
                ['minQuantity' => 100, 'maxQuantity' => 500, 'price' => '4.00'],
            ],
        ]))->assertUnprocessable()->assertJsonValidationErrors('priceTiers');

        $this->assertDatabaseCount('printing_services', 0);

        $this->postJson('/api/v1/printing-services', $this->payload([
            'priceTiers' => [
                ['minQuantity' => 1, 'maxQuantity' => 200, 'price' => '5.00'],
                ['minQuantity' => null, 'maxQuantity' => null, 'price' => '7.00'],
            ],
        ]))->assertCreated()->assertJsonCount(2, 'data.priceTiers');
    }

    public function test_update_applies_mixed_tier_commands_without_deleting_unlisted_tiers(): void
    {
        $service = $this->service();
        $unchanged = $this->tier($service, [
            'min_quantity' => 1, 'max_quantity' => 99, 'price' => '9.00',
        ]);
        $updated = $this->tier($service, [
            'min_quantity' => 100, 'max_quantity' => 200, 'price' => '8.00',
        ]);
        $deleted = $this->tier($service, [
            'min_quantity' => 201, 'max_quantity' => 300, 'price' => '7.00',
        ]);

        $this->putJson('/api/v1/printing-services/'.$service->id, [
            'basePrice' => '12.00',
            'priceTiers' => [
                ['actionStatus' => 0, 'priceTierId' => $unchanged],
                ['actionStatus' => 1, 'customerTypeId' => null, 'minQuantity' => 301, 'maxQuantity' => 500, 'price' => '6.00'],
                ['actionStatus' => 2, 'priceTierId' => $updated, 'customerTypeId' => null, 'minQuantity' => 100, 'maxQuantity' => 200, 'price' => '5.00'],
                ['actionStatus' => 3, 'priceTierId' => $deleted],
            ],
        ])->assertOk()->assertJsonCount(3, 'data.priceTiers');

        $this->assertDatabaseHas('printing_service_price_tiers', ['id' => $updated, 'price' => '5.00']);
        $this->assertSoftDeleted('printing_service_price_tiers', ['id' => $deleted]);
        $this->assertDatabaseHas('printing_service_price_tiers', ['id' => $unchanged, 'price' => '9.00']);
        $this->assertDatabaseHas('printing_services', ['id' => $service->id, 'base_price' => '12.00']);
    }

    public function test_bad_tier_update_rolls_back_service_fields(): void
    {
        $service = $this->service();
        $tierId = $this->tier($service);

        $this->putJson('/api/v1/printing-services/'.$service->id, [
            'name' => 'Renamed Service',
            'priceTiers' => [
                ['actionStatus' => 1, 'minQuantity' => 200, 'maxQuantity' => 600, 'price' => '4.00'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('priceTiers');

        $this->assertDatabaseHas('printing_services', ['id' => $service->id, 'name' => 'Business Cards']);
        $this->assertDatabaseCount('printing_service_price_tiers', 1);
        $this->assertDatabaseHas('printing_service_price_tiers', ['id' => $tierId]);
    }

    public function test_update_and_delete_require_price_tier_id(): void
    {
        $service = $this->service();

        foreach ([2, 3] as $status) {
            $this->patchJson('/api/v1/printing-services/'.$service->id, [
                'priceTiers' => [['actionStatus' => $status]],
            ])->assertUnprocessable()->assertJsonValidationErrors('priceTiers.0.priceTierId');
        }

        $this->patchJson('/api/v1/printing-services/'.$service->id, [
            'priceTiers' => [['actionStatus' => 1, 'priceTierId' => 10, 'price' => '4.00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('priceTiers.0.priceTierId');
    }

    public function test_deleting_never_used_service_soft_deletes_tiers_and_frees_its_name(): void
    {
        $service = $this->service();
        $tierId = $this->tier($service);
        $this->deleteJson('/api/v1/printing-services/'.$service->id)->assertOk();

        $this->assertSoftDeleted('printing_services', ['id' => $service->id]);
        $this->assertSoftDeleted('printing_service_price_tiers', ['id' => $tierId]);
        $this->postJson('/api/v1/printing-services', $this->payload())->assertCreated();
    }

    public function test_name_only_search_and_status_filter(): void
    {
        $this->service(['name' => 'Glossy Cards']);
        $this->service(['name' => 'Posters', 'status' => PrintingServiceStatus::INACTIVE]);
        $this->getJson('/api/v1/printing-services?filter[search]=Cards')
            ->assertOk()->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.printingServices.0.name', 'Glossy Cards');
        $this->getJson('/api/v1/printing-services?filter[status]=0')
            ->assertOk()->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.printingServices.0.name', 'Posters');
        $this->getJson('/api/v1/printing-services?filter[price]=10')
            ->assertUnprocessable();
    }

    public function test_guest_and_staff_without_permissions_are_rejected(): void
    {
        $this->flushSession();
        auth()->guard('web')->logout();
        auth()->guard('sanctum')->forgetUser();
        $this->postJson('/api/v1/printing-services', $this->payload())->assertUnauthorized();

        $staff = User::create([
            'name' => 'Staff', 'username' => 'printing_unprivileged',
            'password' => 'password123', 'status' => UserStatus::ACTIVE,
        ]);
        $staff->assignRole($this->staffRole);
        $this->login($staff);
        $this->postJson('/api/v1/printing-services', $this->payload())->assertForbidden();
    }
}

