<?php

namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Orders\Models\OrderItemFile;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class OrderFileArchiveTest extends OrdersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function upload(Order $order, OrderItem $item, string $name, string $content): OrderItemFile
    {
        $this->post($this->path($order).'/items/'.$item->id.'/files', [
            'file' => UploadedFile::fake()->createWithContent($name, $content),
        ], ['Accept' => 'application/json'])->assertCreated();

        return $item->files()->orderByDesc('id')->firstOrFail();
    }

    private function twoItemDraft(): Order
    {
        $items = $this->data()['items'];
        $items[] = [
            'serviceId' => $this->secondService->id,
            'quantity' => 25,
            'agreedTotal' => '200.00',
        ];

        return $this->draft(['items' => $items]);
    }

    public function test_deleting_an_item_archives_its_file_without_touching_remaining_item_files(): void
    {
        $order = $this->twoItemDraft();
        [$first, $second] = $order->items()->orderBy('id')->get()->all();
        $removed = $this->upload($order, $first, 'removed.pdf', '%PDF-1.7 archived');
        $kept = $this->upload($order, $second, 'kept.pdf', '%PDF-1.7 active');

        $this->deleteJson($this->path($order).'/items/'.$first->id)
            ->assertOk()
            ->assertJsonPath('message', __('orders.messages.item_deleted'))
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('order_items', ['id' => $first->id]);
        $this->assertSame('350.00', (string) $order->fresh()->total_amount);

        Storage::disk('public')->assertMissing($removed->path);
        Storage::disk('local')->assertExists($removed->path);
        $this->assertSame('%PDF-1.7 archived', Storage::disk('local')->get($removed->path));
        $this->assertSame('local', $removed->fresh()->disk);

        Storage::disk('public')->assertExists($kept->path);
        Storage::disk('local')->assertMissing($kept->path);
        $this->assertSame('public', $kept->fresh()->disk);
        $this->assertDatabaseHas('order_activities', [
            'order_id' => $order->id, 'event_type' => 'item_removed',
        ]);
    }

    public function test_deleting_a_draft_archives_all_attached_files_preserving_relative_paths(): void
    {
        $order = $this->twoItemDraft();
        [$first, $second] = $order->items()->orderBy('id')->get()->all();
        $left = $this->upload($order, $first, 'front.png', "\x89PNG\r\n\x1A\n".str_repeat("\x00", 24));
        $right = $this->upload($order, $second, 'artwork.pdf', '%PDF-1.7 keep');

        $this->deleteJson($this->path($order))
            ->assertOk()
            ->assertJsonPath('message', __('orders.messages.draft_deleted'))
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        foreach ([$left, $right] as $file) {
            Storage::disk('public')->assertMissing($file->path);
            Storage::disk('local')->assertExists($file->path);
            $this->assertSame('local', $file->fresh()->disk);
            $this->assertStringStartsWith('orders/'.$order->id.'/', $file->path);
        }

        $this->assertDatabaseHas('order_activities', [
            'order_id' => $order->id, 'event_type' => 'draft_deleted',
        ]);
    }

    public function test_archive_error_rolls_back_file_moves_and_draft_deletion(): void
    {
        $order = $this->twoItemDraft();
        [$first, $second] = $order->items()->orderBy('id')->get()->all();
        $good = $this->upload($order, $first, 'good.pdf', '%PDF-1.7 good');
        $missing = $this->upload($order, $second, 'missing.pdf', '%PDF-1.7 missing');

        // A failure after the first file has already been moved must put the
        // original file back on the public disk and roll back the database.
        Storage::disk('public')->delete($missing->path);

        $this->deleteJson($this->path($order))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        Storage::disk('public')->assertExists($good->path);
        Storage::disk('local')->assertMissing($good->path);
        $this->assertSame('%PDF-1.7 good', Storage::disk('public')->get($good->path));
        $this->assertSame('public', $good->fresh()->disk);
        $this->assertSame('public', $missing->fresh()->disk);
        $this->assertNull($order->fresh()->deleted_at);
        $this->assertDatabaseMissing('order_activities', [
            'order_id' => $order->id, 'event_type' => 'draft_deleted',
        ]);
    }

    public function test_private_destination_collision_is_rejected_without_overwriting_files(): void
    {
        $order = $this->draft();
        $item = $order->items()->firstOrFail();
        $file = $this->upload($order, $item, 'artwork.pdf', '%PDF-1.7 original');

        Storage::disk('local')->put($file->path, 'existing private archive');

        $this->deleteJson($this->path($order).'/items/'.$item->id.'/files/'.$file->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame('existing private archive', Storage::disk('local')->get($file->path));
        $this->assertSame('%PDF-1.7 original', Storage::disk('public')->get($file->path));
        $this->assertSame('public', $file->fresh()->disk);
        $this->assertNull($file->fresh()->deleted_at);
    }

    public function test_file_already_in_private_storage_is_soft_deleted_without_relocation(): void
    {
        $order = $this->draft();
        $item = $order->items()->firstOrFail();
        $file = $this->upload($order, $item, 'old.pdf', '%PDF-1.7 already private');

        Storage::disk('local')->put($file->path, Storage::disk('public')->get($file->path));
        Storage::disk('public')->delete($file->path);
        $file->disk = 'local';
        $file->save();

        $this->deleteJson($this->path($order).'/items/'.$item->id.'/files/'.$file->id)
            ->assertOk()->assertJsonPath('data', null);

        Storage::disk('public')->assertMissing($file->path);
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame('local', $file->fresh()->disk);
        $this->assertSoftDeleted('order_item_files', ['id' => $file->id]);
    }
}
