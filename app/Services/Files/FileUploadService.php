<?php

namespace App\Services\Files;

use App\Exceptions\Files\FileUploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/** General-purpose file storage. File type/size validation belongs to FormRequest. */
class FileUploadService
{
    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $directory = $this->normalizeDirectory($directory);

        try {
            $path = $file->store($directory, $disk);
        } catch (Throwable $exception) {
            Log::error('File storage failed.', ['exception' => $exception]);
            throw new FileUploadException();
        }

        if (! is_string($path) || $path === '') {
            throw new FileUploadException();
        }

        return $path;
    }

    /**
     * Best-effort cleanup. Never delete files outside the directory owned by the caller.
     * A failed cleanup is logged because the database transaction has already succeeded.
     */
    public function delete(?string $path, string $directory, string $disk = 'public'): void
    {
        $directory = $this->normalizeDirectory($directory);

        if ($path === null || ! str_starts_with($path, $directory.'/')) {
            return;
        }

        try {
            $storage = Storage::disk($disk);

            if ($storage->exists($path) && ! $storage->delete($path)) {
                Log::warning('Could not delete stored file.', [
                    'disk' => $disk,
                    'directory' => $directory,
                    'path' => $path,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Could not delete stored file.', [
                'disk' => $disk,
                'directory' => $directory,
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizeDirectory(string $directory): string
    {
        $directory = trim($directory, '/');

        if ($directory === '' || str_contains($directory, '..') || str_contains($directory, '\\')) {
            throw new InvalidArgumentException('A safe storage directory is required.');
        }

        return $directory;
    }
}
