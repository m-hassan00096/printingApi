<?php
namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class OrdersFilesTest extends OrdersTestCase
{
    public function test_files_are_private_downloadable_and_soft_deletable(): void
    {
        Storage::fake('local');
        $order = $this->draft();
        $item = $order->items()->firstOrFail();
        $path = $this->path($order).'/items/'.$item->id.'/files';

        $created = $this->post($path, [
            'file' => UploadedFile::fake()->createWithContent('artwork.pdf', '%PDF-1.7 Test'),
        ], ['Accept' => 'application/json']);

        $created->assertCreated()
            ->assertJsonPath('message', __('orders.messages.file_uploaded'))
            ->assertJsonPath('data.name', 'artwork.pdf')
            ->assertJsonMissingPath('data.path');

        $fileId = $created->json('data.id');

        $this->get($path.'/'.$fileId)->assertOk();
        $this->deleteJson($path.'/'.$fileId)->assertOk();

        $this->assertSoftDeleted('order_item_files', ['id' => $fileId]);
        $this->get($path.'/'.$fileId)->assertNotFound();
    }

    public function test_rejects_bad_upload_extensions(): void
    {
        Storage::fake('local');
        $order = $this->draft();
        $item = $order->items()->first();
        $path = $this->path($order).'/items/'.$item->id.'/files';

        $this->post($path, [
            'file' => UploadedFile::fake()->create('evil.exe', 1),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_nested_order_item_file_access_is_scoped(): void
    {
        Storage::fake('local');
        $first = $this->draft();
        $second = $this->draft(['customerId' => $this->secondCustomer->id]);

        $item = $first->items()->first();
        $path = $this->path($first).'/items/'.$item->id.'/files';
        $created = $this->post($path, [
            'file' => UploadedFile::fake()->createWithContent('notes.pdf', '%PDF-1.7'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->get($this->path($second).'/items/'.$item->id.'/files/'.$created->json('data.id'))
            ->assertNotFound();
        $this->get($this->path($first).'/items/'.$item->id.'/files/'.$created->json('data.id'))
            ->assertOk();
    }
}
