<?php

namespace App\Backup;

use App\Models\BackupRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BackupService
{
    public function __construct(private ?Destination $destination = null) {}

    public function destination(): Destination
    {
        return $this->destination ??= config('backup.destination') === 'google_drive'
            ? new GoogleDriveDestination
            : new LocalDestination;
    }

    /** Make, encrypt, upload, tidy. Emails the owner if anything fails. */
    public function run(): BackupRun
    {
        $tmp = storage_path('app/private/tmp');
        @mkdir($tmp, 0700, true);
        $name = 'crm-backup-'.now()->format('Y-m-d-Hi').'.enc';
        $gz = "$tmp/backup.jsonl.gz";
        $enc = "$tmp/$name";

        try {
            $counts = DatabaseDumper::dump($gz);
            BackupCrypto::encryptFile($gz, $enc);
            $size = filesize($enc);
            $this->destination()->put($enc, $name);
            $removed = $this->applyRetention();

            return BackupRun::create(['kind' => 'backup', 'status' => 'success', 'file_name' => $name, 'size_bytes' => $size,
                'message' => sprintf('%d students, %d call entries; saved to %s; %d old copies removed',
                    $counts['students'] ?? 0, $counts['call_entries'] ?? 0, $this->destination()->label(), count($removed))]);
        } catch (Throwable $e) {
            return $this->fail('backup', $name, $e);
        } finally {
            @unlink($gz);
            @unlink($enc);
        }
    }

    /**
     * Keeps the newest copy of each of the last 7 days, the last 4 weeks
     * and the last 3 months; removes the rest.
     *
     * @return list<string> removed names
     */
    public function applyRetention(): array
    {
        $keep = config('backup.keep');
        $dated = [];
        foreach ($this->destination()->list() as $file) {
            if (preg_match('/^crm-backup-(\d{4}-\d{2}-\d{2})-(\d{4})\.enc$/', $file, $m)) {
                $dated[$file] = Carbon::createFromFormat('Y-m-d Hi', $m[1].' '.$m[2]);
            }
        }
        arsort($dated); // newest first

        $kept = [];
        foreach (['daily' => 'Y-m-d', 'weekly' => 'o-W', 'monthly' => 'Y-m'] as $tier => $fmt) {
            $seen = [];
            foreach ($dated as $file => $at) {
                $bucket = $at->format($fmt);
                if (! isset($seen[$bucket]) && count($seen) < $keep[$tier]) {
                    $seen[$bucket] = true;
                    $kept[$file] = true;
                }
            }
        }

        $removed = [];
        foreach (array_keys($dated) as $file) {
            if (! isset($kept[$file])) {
                $this->destination()->delete($file);
                $removed[] = $file;
            }
        }

        return $removed;
    }

    /**
     * Restores the newest backup into a separate, empty test database and
     * checks every table's row count matches. Never touches the live database.
     */
    public function restoreTest(): BackupRun
    {
        $conn = config('backup.restore_connection');
        $tmp = storage_path('app/private/tmp');
        @mkdir($tmp, 0700, true);
        $enc = "$tmp/restore.enc";
        $gz = "$tmp/restore.jsonl.gz";
        $latest = null;

        try {
            if ($conn === config('database.default') || config("database.connections.$conn.database") === config('database.connections.'.config('database.default').'.database')) {
                throw new \RuntimeException('The restore test database must be different from the live database.');
            }
            $files = array_filter($this->destination()->list(), fn ($f) => str_starts_with($f, 'crm-backup-'));
            rsort($files);
            $latest = $files[0] ?? throw new \RuntimeException('No backup found to test.');

            $this->destination()->get($latest, $enc);
            BackupCrypto::decryptFile($enc, $gz);

            Artisan::call('migrate:fresh', ['--database' => $conn, '--force' => true]);
            [$loaded, $expected] = DatabaseDumper::load($gz, $conn);
            $actual = [];
            foreach (array_keys($expected) as $t) {
                $actual[$t] = DB::connection($conn)->table($t)->count();
            }
            $bad = array_keys(array_diff_assoc($expected, $actual));
            if ($bad) {
                throw new \RuntimeException('Row counts differ after restore in: '.implode(', ', $bad));
            }

            return BackupRun::create(['kind' => 'restore_test', 'status' => 'success', 'file_name' => $latest,
                'message' => sprintf('Restored and checked %d tables (%d students).', count($expected), $expected['students'] ?? 0)]);
        } catch (Throwable $e) {
            return $this->fail('restore_test', $latest, $e);
        } finally {
            @unlink($enc);
            @unlink($gz);
        }
    }

    private function fail(string $kind, ?string $name, Throwable $e): BackupRun
    {
        Log::error("CRM $kind failed", ['error' => $e->getMessage()]);
        $run = BackupRun::create(['kind' => $kind, 'status' => 'failed', 'file_name' => $name, 'message' => mb_substr($e->getMessage(), 0, 1000)]);

        if ($to = config('backup.alert_email')) {
            try {
                $what = $kind === 'backup' ? 'nightly backup' : 'monthly restore test';
                Mail::to($to)->send(new \App\Mail\BackupFailed($what, $e->getMessage()));
            } catch (Throwable $mailError) {
                Log::error('Could not send backup failure email', ['error' => $mailError->getMessage()]);
            }
        }

        return $run;
    }
}
