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

    public function test_rejects_executable_content_disguised_as_supported_file(): void
    {
        Storage::fake('local');
        $order = $this->draft();
        $item = $order->items()->firstOrFail();
        $path = $this->path($order).'/items/'.$item->id.'/files';

        foreach (['fake.pdf', 'fake.png', 'fake.psd', 'fake.docx'] as $name) {
            $this->post($path, [
                'file' => UploadedFile::fake()->createWithContent($name, '<?php echo "unsafe"; ?>'),
            ], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');
        }

        $this->assertDatabaseCount('order_item_files', 0);
    }

    public function test_valid_png_signature_is_accepted_without_exposing_internal_path(): void
    {
        Storage::fake('local');
        $order = $this->draft();
        $item = $order->items()->firstOrFail();
        $path = $this->path($order).'/items/'.$item->id.'/files';

        $this->post($path, [
            'file' => UploadedFile::fake()->createWithContent(
                'logo.png', "\x89PNG\r\n\x1A\n".str_repeat("\x00", 48),
            ),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.extension', 'png')
            ->assertJsonMissingPath('data.path');
    }

    public function test_failed_upload_to_confirmed_order_removes_staged_file(): void
    {
        Storage::fake('local');
        $order = $this->draft();
        $order->status = \Modules\Orders\Enums\OrderStatus::CONFIRMED;
        $order->save();
        $item = $order->items()->firstOrFail();

        $this->post($this->path($order).'/items/'.$item->id.'/files', [
            'file' => UploadedFile::fake()->createWithContent('design.pdf', '%PDF-1.7 Content'),
        ], ['Accept' => 'application/json'])->assertStatus(409);

        $this->assertDatabaseCount('order_item_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('orders'));
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
