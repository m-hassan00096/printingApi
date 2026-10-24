<?php

namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Orders\Models\OrderItemFile;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class OrderFilePublicUrlTest extends OrdersTestCase
{
    private function upload(string $name, string $contents): array
    {
        $order = $this->draft();
        $item = $order->items()->firstOrFail();
        $base = $this->path($order).'/items/'.$item->id.'/files';

        $response = $this->post($base, [
            'file' => UploadedFile::fake()->createWithContent($name, $contents),
        ], ['Accept' => 'application/json'])->assertCreated();

        return [$order, $item, $base, $response->json('data')];
    }

    public function test_upload_stores_file_on_public_disk_and_returns_a_direct_permanent_storage_url(): void
    {
        Storage::fake('public');
        [$order, $item, $base, $file] = $this->upload('artwork.pdf', '%PDF-1.7 document');

        $record = OrderItemFile::findOrFail($file['id']);
        $this->assertSame('public', $record->disk);
        $this->assertSame('artwork.pdf', $file['name']);
        Storage::disk('public')->assertExists($record->path);

        $this->assertSame(Storage::disk('public')->url($record->path), $file['url']);
        $this->assertStringContainsString('/storage/orders/'.$order->id.'/'.$item->id.'/', $file['url']);
        $this->assertStringEndsWith('.pdf', $file['url']);
        $this->assertStringNotContainsString('signature=', $file['url']);
        $this->assertStringNotContainsString('expires=', $file['url']);
        $this->assertArrayNotHasKey('expiresAt', $file);
        $this->assertArrayNotHasKey('uploadedBy', $file);
        $this->assertArrayNotHasKey('path', $file);
        $this->assertArrayNotHasKey('disk', $file);

        $this->getJson($base)->assertOk()
            ->assertJsonPath('data.0.id', $file['id'])
            ->assertJsonPath('data.0.url', $file['url'])
            ->assertJsonMissingPath('data.0.uploadedBy');

        $this->getJson($this->path($order))->assertOk()
            ->assertJsonPath('data.items.0.files.0.url', $file['url'])
            ->assertJsonMissingPath('data.items.0.files.0.uploadedBy');

        // Actual HTTP serving of /storage is done by the webserver symlink
        // (php artisan storage:link), not by Laravel's API request pipeline.
    }

    public function test_pdf_and_png_public_files_have_the_expected_extensions_for_browser_inline_display(): void
    {
        Storage::fake('public');

        [, , , $pdf] = $this->upload('artwork.pdf', '%PDF-1.7 document');
        [, , , $png] = $this->upload('logo.png', "\x89PNG\r\n\x1A\n".str_repeat("\x00", 48));

        $this->assertStringEndsWith('.pdf', $pdf['url']);
        $this->assertStringEndsWith('.png', $png['url']);
        $this->assertNotNull($pdf['url']);
        $this->assertNotNull($png['url']);
    }

    public function test_listing_files_returns_public_url_even_without_files_view_permission(): void
    {
        Storage::fake('public');
        [$order, $item, $base, $file] = $this->upload('artwork.pdf', '%PDF-1.7 document');

        $staff = User::create([
            'name' => 'File Listing Clerk',
            'username' => 'file_listing_clerk',
            'password' => 'password123',
            'status' => UserStatus::ACTIVE,
        ]);
        $staff->assignRole($this->staffRole);
        $this->staffRole->givePermissionTo([
            Permission::findOrCreate('orders.view', 'web'),
            Permission::findOrCreate('orders.files.list', 'web'),
        ]);
        $this->login($staff);

        $this->getJson($base)->assertOk()
            ->assertJsonPath('data.0.url', $file['url']);

        $this->getJson($this->path($order))->assertOk()
            ->assertJsonPath('data.items.0.files.0.url', $file['url']);

        // The authenticated file API still enforces its distinct permission.
        $this->get($base.'/'.$file['id'])->assertForbidden();

        $this->flushSession();
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();

        $this->getJson($base)->assertUnauthorized();
        // Public file bytes are still present; a web server serves this path
        // without Laravel authentication, as requested.
        Storage::disk('public')->assertExists(OrderItemFile::findOrFail($file['id'])->path);
    }

    public function test_details_hide_files_metadata_from_users_without_files_list_permission(): void
    {
        Storage::fake('public');
        [$order] = $this->upload('artwork.pdf', '%PDF-1.7 document');

        $staff = User::create([
            'name' => 'Order Details Clerk',
            'username' => 'order_details_clerk',
            'password' => 'password123',
            'status' => UserStatus::ACTIVE,
        ]);
        $staff->assignRole($this->staffRole);
        $this->staffRole->givePermissionTo(Permission::findOrCreate('orders.view', 'web'));
        $this->login($staff);

        $this->getJson($this->path($order))->assertOk()
            ->assertJsonMissingPath('data.items.0.files');
    }

    public function test_legacy_private_record_does_not_generate_broken_public_url(): void
    {
        Storage::fake('public');
        [$order, , $base, $file] = $this->upload('artwork.pdf', '%PDF-1.7 document');
        $record = OrderItemFile::findOrFail($file['id']);
        $record->disk = 'local';
        $record->save();

        $this->getJson($base)->assertOk()
            ->assertJsonPath('data.0.url', null);
    }

    public function test_nested_file_list_rejects_items_belonging_to_other_orders(): void
    {
        Storage::fake('public');
        [$order, $item, , $file] = $this->upload('artwork.pdf', '%PDF-1.7 document');
        $other = $this->draft(['customerId' => $this->secondCustomer->id]);

        $this->getJson($this->path($other).'/items/'.$item->id.'/files')->assertNotFound();
        $this->getJson($this->path($order).'/items/'.$item->id.'/files')
            ->assertOk()->assertJsonPath('data.0.id', $file['id']);
    }
}
