<?php
namespace Modules\Orders\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Orders\Models\OrderItemFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderFileService
{
    private const EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'psd', 'ai',
    ];

    public function __construct(
        private readonly DraftOrderService $drafts,
        private readonly OrderActivityWriter $activities,
    ) {}

    public function upload(Order $order, OrderItem $item, UploadedFile $upload): OrderItemFile
    {
        $this->assertItemBelongs($order, $item);

        $extension = strtolower($upload->getClientOriginalExtension());
        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => __('orders.messages.file_extension')]);
        }
        if ($upload->getSize() > 100 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => __('orders.messages.file_too_large')]);
        }

        $disk = Storage::disk('local');
        $path = null;

        try {
            return DB::transaction(function () use ($order, $item, $upload, $disk, $extension, &$path): OrderItemFile {
                $locked = $this->drafts->lockedDraft($order);
                $item = $locked->items()->whereKey($item->id)->lockForUpdate()->firstOrFail();

                $directory = 'orders/'.$locked->id.'/'.$item->id;
                $filename = (string) Str::uuid().'.'.$extension;
                $path = $disk->putFileAs($directory, $upload, $filename);

                if ($path === false) {
                    throw ValidationException::withMessages(['file' => __('orders.messages.file_unavailable')]);
                }

                $original = basename(str_replace('\\', '/', $upload->getClientOriginalName()));
                $original = mb_substr(str_replace(["\r", "\n"], '', $original), 0, 255);

                $record = $item->files()->create([
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $original,
                    'extension' => $extension,
                    'mime_type' => $upload->getMimeType(),
                    'size_bytes' => $upload->getSize(),
                    'uploaded_by' => Auth::id(),
                ]);

                $this->activities->record($locked, 'file_uploaded', [
                    'itemId' => $item->id, 'fileId' => $record->id,
                ]);

                return $record;
            }, 3);
        } catch (\Throwable $exception) {
            if (is_string($path)) {
                $disk->delete($path);
            }
            throw $exception;
        }
    }

    public function download(Order $order, OrderItem $item, OrderItemFile $file): StreamedResponse
    {
        $this->assertFileBelongs($order, $item, $file);
        $disk = Storage::disk($file->disk);
        if (! $disk->exists($file->path)) {
            throw ValidationException::withMessages(['file' => __('orders.messages.file_unavailable')]);
        }

        return $disk->download($file->path, $file->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(Order $order, OrderItem $item, OrderItemFile $file): void
    {
        $this->assertFileBelongs($order, $item, $file);

        DB::transaction(function () use ($order, $item, $file): void {
            $locked = $this->drafts->lockedDraft($order);
            $lockedItem = $locked->items()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $record = $lockedItem->files()->whereKey($file->id)->lockForUpdate()->firstOrFail();
            $record->delete();
            $this->activities->record($locked, 'file_removed', [
                'itemId' => $item->id, 'fileId' => $file->id,
            ]);
        }, 3);
    }

    private function assertItemBelongs(Order $order, OrderItem $item): void
    {
        abort_unless($item->order_id === $order->id, 404);
    }

    private function assertFileBelongs(Order $order, OrderItem $item, OrderItemFile $file): void
    {
        $this->assertItemBelongs($order, $item);
        abort_unless($file->order_item_id === $item->id, 404);
    }
}
