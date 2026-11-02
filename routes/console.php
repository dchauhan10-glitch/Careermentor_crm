<?php

use App\Backup\BackupCrypto;
use App\Backup\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('crm:backup', function (BackupService $backups) {
    $run = $backups->run();
    $this->{$run->status === 'success' ? 'info' : 'error'}("Backup {$run->status}: {$run->message}");

    return $run->status === 'success' ? 0 : 1;
})->purpose('Make an encrypted database backup now');

Artisan::command('crm:backup-restore-test', function (BackupService $backups) {
    $run = $backups->restoreTest();
    $this->{$run->status === 'success' ? 'info' : 'error'}("Restore test {$run->status}: {$run->message}");

    return $run->status === 'success' ? 0 : 1;
})->purpose('Restore the newest backup into the test database and check it');

Artisan::command('crm:backup-key', function () {
    $this->line('Add this line to .env, and keep a copy somewhere safe OFF the server:');
    $this->line('BACKUP_ENCRYPTION_KEY='.BackupCrypto::newKey());
})->purpose('Create a new backup encryption key');

Artisan::command('crm:backup-decrypt {file} {out}', function (string $file, string $out) {
    BackupCrypto::decryptFile($file, $out);
    $this->info("Decrypted to $out (gzip of one JSON row per line).");
})->purpose('Unlock a downloaded backup file (needs BACKUP_ENCRYPTION_KEY)');

/*
 * Shared hosting: one cron line runs `php artisan schedule:run` every minute.
 * No program has to stay running in the background.
 *
 * Hostinger turns off PHP's ability to start other programs (proc_open), so
 * each task runs inside the same PHP process with Schedule::call instead of
 * Schedule::command (which would start a new process).
 */
$inProcess = fn (string $command, array $options = []) => fn () => Artisan::call($command, $options);

Schedule::call($inProcess('queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3]))
    ->name('crm-queue')->everyMinute()->withoutOverlapping(5);
Schedule::call($inProcess('crm:backup'))
    ->name('crm-backup')->dailyAt('02:00')->withoutOverlapping(60);
Schedule::call($inProcess('crm:backup-restore-test'))
    ->name('crm-restore-test')->monthlyOn(1, '03:30')->withoutOverlapping(60);
Schedule::call($inProcess('queue:prune-failed', ['--hours' => 720]))
    ->name('crm-prune-failed')->weekly();
