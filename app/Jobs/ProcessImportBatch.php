<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\ImportService;
use App\Services\LeadIntakeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Works through an import a few hundred rows at a time. Run by the
 * once-a-minute scheduled task; each run stops well before hosting time limits.
 */
class ProcessImportBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 50;

    public function __construct(public int $batchId) {}

    public function handle(ImportService $imports, LeadIntakeService $intake): void
    {
        $batch = ImportBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        $started = microtime(true);
        do {
            $finished = $imports->processNext($batch, $intake);
            $batch->refresh();
        } while (! $finished && microtime(true) - $started < 25);

        if (! $finished) {
            self::dispatch($this->batchId); // carry on in the next run
        }
    }

    public function failed(?Throwable $e): void
    {
        ImportBatch::whereKey($this->batchId)->update(['status' => 'failed']);
    }
}
