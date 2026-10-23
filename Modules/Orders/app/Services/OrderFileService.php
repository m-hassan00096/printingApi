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

        if (! $this->matchesFileSignature($upload, $extension)) {
            throw ValidationException::withMessages(['file' => __('orders.messages.file_content_invalid')]);
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

    /**
     * Extension alone is user-controlled. Validate a minimal file magic header,
     * including legacy OLE Office files and ZIP-based modern Office files.
     *
     * This is file-type screening, not malware scanning; files remain private
     * and are always delivered as attachments rather than executed or previewed.
     */
    private function matchesFileSignature(UploadedFile $upload, string $extension): bool
    {
        $handle = fopen($upload->getRealPath(), 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            $header = fread($handle, 16);
        } finally {
            fclose($handle);
        }

        if (! is_string($header)) {
            return false;
        }

        $starts = static fn (string $magic): bool => str_starts_with($header, $magic);
        $pdf = $starts('%PDF-');
        $zip = $starts("PK\x03\x04");
        $ole = $starts("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");

        return match ($extension) {
            'pdf' => $pdf,
            'png' => $starts("\x89PNG\r\n\x1A\n"),
            'jpg', 'jpeg' => $starts("\xFF\xD8\xFF"),
            'gif' => $starts('GIF87a') || $starts('GIF89a'),
            'webp' => $starts('RIFF') && substr($header, 8, 4) === 'WEBP',
            'bmp' => $starts('BM'),
            'tif', 'tiff' => $starts("II\x2A\x00") || $starts("MM\x00\x2A"),
            'psd' => $starts('8BPS'),
            'ai' => $pdf || $starts('%!PS'),
            'doc', 'xls' => $ole,
            'docx', 'xlsx' => $zip,
            default => false,
        };
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
