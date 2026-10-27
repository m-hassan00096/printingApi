<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Orders\Models\OrderItemFile;

/**
 * Detect inconsistent file placement after a crash between a physical move
 * and the transaction commit. Strictly read-only: never move or delete files.
 */
class AuditOrderFiles extends Command
{
    protected $signature = 'orders:audit-files {--max-lines=20 : Maximum anomalies printed}';

    protected $description = 'Read-only consistency check for active and archived order files';

    public function handle(): int
    {
        $checked = 0;
        $mismatches = 0;
        $limit = max(0, min(500, (int) $this->option('max-lines')));
        OrderItemFile::withTrashed()->orderBy('id')->chunkById(200, function ($files) use (&$checked, &$mismatches, $limit): void {
            foreach ($files as $file) {
                $checked++;
                $path = (string) $file->path;
                $public = Storage::disk('public')->exists($path);
                $private = Storage::disk('local')->exists($path);
                $expected = $file->disk;
                $issue = match (true) {
                    ! in_array($expected, ['public', 'local'], true) => 'INVALID_DISK',
                    $public && $private => 'BOTH_DISKS',
                    ! $public && ! $private => 'MISSING',
                    $expected === 'public' && ! $public => 'EXPECTED_PUBLIC_FOUND_PRIVATE',
                    $expected === 'local' && ! $private => 'EXPECTED_PRIVATE_FOUND_PUBLIC',
                    default => null,
                };
                if ($issue === null) continue;

                $mismatches++;
                if ($mismatches <= $limit) {
                    // IDs only: avoid logging customers' original filenames.
                    $this->warn("file_id={$file->id} issue={$issue} disk={$expected}");
                }
            }
        });

        $this->line("Audited {$checked} file records; inconsistencies: {$mismatches}.");
        if ($mismatches > 0) {
            $this->error('Do not automate recovery: inspect DB record, both disks and backups, then reconcile after approval.');
            return self::FAILURE;
        }

        $this->info('File locations match their database records.');
        return self::SUCCESS;
    }
}
