<?php

namespace Modules\Orders\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Orders\Models\OrderItemFile;

/**
 * Archive uploaded files when a file, item or draft is soft-deleted.
 *
 * Both Laravel local disks live on the same server: rename keeps the exact
 * relative path while removing the file from the publicly served directory.
 * A DB failure restores all moved files to their original public locations.
 */
class OrderFileArchiveService
{
    public function transaction(callable $action): mixed
    {
        $moves = [];

        try {
            // No automatic retry: physical filesystem moves are not DB
            // operations and must not be replayed after a deadlock.
            return DB::transaction(
                function () use ($action, &$moves): mixed {
                    return $action($moves);
                },
                1,
            );
        } catch (\Throwable $exception) {
            foreach (array_reverse($moves) as $move) {
                try {
                    File::ensureDirectoryExists(dirname($move['public']));
                    if (! File::move($move['private'], $move['public'])) {
                        throw new \RuntimeException('Could not restore public file after DB rollback.');
                    }
                } catch (\Throwable $restoreException) {
                    Log::critical('Orders file archive compensation failed', [
                        'exception' => $restoreException->getMessage(),
                        'path' => $move['relative'],
                    ]);

                    throw new \RuntimeException(
                        'Order file archive failed and could not restore the original file.',
                        0,
                        $restoreException,
                    );
                }
            }

            throw $exception;
        }
    }

    public function archive(OrderItemFile $record, array &$moves): void
    {
        // Files uploaded by earlier implementations may already be private.
        if ($record->disk === 'local') {
            return;
        }

        if ($record->disk !== 'public') {
            throw ValidationException::withMessages([
                'file' => __('orders.messages.file_unavailable'),
            ]);
        }

        $relative = $record->path;
        $public = Storage::disk('public')->path($relative);
        $private = Storage::disk('local')->path($relative);

        if (! File::isFile($public) || File::exists($private)) {
            throw ValidationException::withMessages([
                'file' => __('orders.messages.file_unavailable'),
            ]);
        }

        File::ensureDirectoryExists(dirname($private));

        if (! File::move($public, $private)) {
            throw ValidationException::withMessages([
                'file' => __('orders.messages.file_unavailable'),
            ]);
        }

        // Register the move before changing the DB record, so any subsequent
        // database/audit failure can put the public file back.
        $moves[] = ['relative' => $relative, 'public' => $public, 'private' => $private];

        $record->disk = 'local';
        $record->save();
    }
}
