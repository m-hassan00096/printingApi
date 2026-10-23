<?php

namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class OrderFileSignedUrlTest extends OrdersTestCase
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

    public function test_files_list_and_detail_include_signed_urls_without_leaking_private_storage_paths(): void
    {
        Storage::fake('local');
        [$order, $item, $base, $file] = $this->upload('artwork.pdf', '%PDF-1.7 demo document');

        $this->assertSame('artwork.pdf', $file['name']);
        $this->assertNotNull($file['url']);
        $this->assertNotNull($file['expiresAt']);
        $this->assertStringContainsString('/preview/orders/files/'.$file['id'], $file['url']);
        $this->assertStringContainsString('signature=', $file['url']);
        $this->assertStringContainsString('expires=', $file['url']);
        $this->assertStringNotContainsString('app/private', $file['url']);
        $this->assertStringNotContainsString('/orders/'.$order->id.'/'.$item->id.'/', $file['url']);

        $this->getJson($base)->assertOk()
            ->assertJsonPath('message', '')
            ->assertJsonPath('data.0.id', $file['id'])
            ->assertJsonPath('data.0.name', 'artwork.pdf')
            ->assertJsonMissingPath('data.0.path')
            ->assertJsonMissingPath('data.0.disk');

        $this->getJson($this->path($order))->assertOk()
            ->assertJsonPath('data.items.0.files.0.id', $file['id'])
            ->assertJsonPath('data.items.0.files.0.name', 'artwork.pdf');

        $this->assertDatabaseHas('order_item_files', [
            'id' => $file['id'], 'disk' => 'local',
        ]);
    }

    public function test_signed_pdf_previews_inline_without_authentication(): void
    {
        Storage::fake('local');
        [$order, $item, $base, $file] = $this->upload('artwork.pdf', '%PDF-1.7 demo document');

        $this->flushSession();
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();

        $response = $this->get($file['url'])->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertStringStartsWith(
            'inline;', strtolower((string) $response->headers->get('Content-Disposition')),
        );

        // A valid URL is a short-lived read-only capability, not a session.
        $this->getJson($base)->assertUnauthorized();
    }

    public function test_png_uses_inline_image_content_type_and_office_is_attachment(): void
    {
        Storage::fake('local');
        [, , , $image] = $this->upload(
            'logo.png', "\x89PNG\r\n\x1A\n".str_repeat("\x00", 48),
        );

        $png = $this->get($image['url'])->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith('inline;', strtolower((string) $png->headers->get('Content-Disposition')));

        [, , , $office] = $this->upload('proposal.docx', "PK\x03\x04".str_repeat("\x00", 48));
        $download = $this->get($office['url'])->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream');

        $this->assertStringStartsWith(
            'attachment;', strtolower((string) $download->headers->get('Content-Disposition')),
        );
    }

    public function test_unsigned_tampered_and_expired_links_cannot_fetch_file(): void
    {
        Storage::fake('local');
        [, , , $file] = $this->upload('artwork.pdf', '%PDF-1.7 doc');

        $this->get('/preview/orders/files/'.$file['id'])->assertForbidden();

        $tampered = preg_replace('/signature=[a-f0-9]+/', 'signature=12345', $file['url']);
        $this->get($tampered)->assertForbidden();

        $this->travel(16)->minutes();
        try {
            $this->get($file['url'])->assertForbidden();
        } finally {
            $this->travelBack();
        }
    }

    public function test_signed_url_is_revoked_when_file_or_parent_is_soft_deleted(): void
    {
        Storage::fake('local');
        [$order, $item, $base, $file] = $this->upload('artwork.pdf', '%PDF-1.7 doc');

        $this->get($file['url'])->assertOk();
        $this->deleteJson($base.'/'.$file['id'])->assertOk();
        $this->get($file['url'])->assertNotFound();

        [$secondOrder, , , $other] = $this->upload('other.pdf', '%PDF-1.7 doc');
        $this->deleteJson($this->path($secondOrder))->assertOk();
        $this->get($other['url'])->assertNotFound();
    }

    public function test_file_metadata_permission_does_not_issue_preview_capability_without_view_permission(): void
    {
        Storage::fake('local');
        [$order, $item, $base, $file] = $this->upload('artwork.pdf', '%PDF-1.7 doc');

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
            ->assertJsonPath('data.0.id', $file['id'])
            ->assertJsonPath('data.0.url', null)
            ->assertJsonPath('data.0.expiresAt', null);

        $this->getJson($this->path($order))->assertOk()
            ->assertJsonPath('data.items.0.files.0.url', null);

        $this->get($base.'/'.$file['id'])->assertForbidden();
        $this->post($base, [
            'file' => UploadedFile::fake()->createWithContent('try.pdf', '%PDF-1.7'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->staffRole->givePermissionTo(Permission::findOrCreate('orders.files.view', 'web'));
        $this->getJson($base)->assertOk()
            ->assertJsonPath('data.0.id', $file['id']);

        $this->assertNotNull($this->getJson($base)->json('data.0.url'));
        $this->get($base.'/'.$file['id'])->assertOk();
    }

    public function test_order_details_hide_file_list_from_users_without_files_list_permission(): void
    {
        Storage::fake('local');
        [$order] = $this->upload('artwork.pdf', '%PDF-1.7 doc');

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
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonMissingPath('data.items.0.files');
    }

    public function test_nested_files_list_is_restricted_to_parent_order(): void
    {
        Storage::fake('local');
        [$first, $item, , $file] = $this->upload('artwork.pdf', '%PDF-1.7 doc');
        $second = $this->draft(['customerId' => $this->secondCustomer->id]);

        $this->getJson($this->path($second).'/items/'.$item->id.'/files')->assertNotFound();
        $this->getJson($this->path($first).'/items/'.$item->id.'/files')
            ->assertOk()->assertJsonPath('data.0.id', $file['id']);
    }
}
