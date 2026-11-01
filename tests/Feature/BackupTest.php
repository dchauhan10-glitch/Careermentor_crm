<?php

namespace Tests\Feature;

use App\Backup\BackupCrypto;
use App\Backup\BackupService;
use App\Backup\LocalDestination;
use App\Models\BackupRun;
use App\Models\CallEntry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class BackupTest extends CrmTestCase
{
    private string $restoreDb;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['backup.encryption_key' => BackupCrypto::newKey(), 'backup.alert_email' => 'owner@example.test']);
        $this->restoreDb = tempnam(sys_get_temp_dir(), 'restore').'.sqlite';
        touch($this->restoreDb);
        config(['database.connections.restore_test' => ['driver' => 'sqlite', 'database' => $this->restoreDb, 'prefix' => '', 'foreign_key_constraints' => true]]);
    }

    protected function tearDown(): void
    {
        @unlink($this->restoreDb);
        parent::tearDown();
    }

    public function test_backup_is_encrypted_and_restore_test_passes(): void
    {
        $s = $this->student('9876543210', $this->c1, 'Backup Student');
        app(\App\Services\LeadWorkService::class)->logCall($s, $this->c1, ['outcome' => 'Spoke']);

        $run = app(BackupService::class)->run();
        $this->assertSame('success', $run->status, $run->message);
        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $raw = Storage::disk('local')->get($files[0]);
        $this->assertStringNotContainsString('9876543210', $raw);
        $this->assertStringNotContainsString('Backup Student', $raw);

        $test = app(BackupService::class)->restoreTest();
        $this->assertSame('success', $test->status, $test->message);
        $this->assertSame('Backup Student', \DB::connection('restore_test')->table('students')->value('name'));
        $this->assertSame(1, \DB::connection('restore_test')->table('call_entries')->count());
    }

    public function test_wrong_key_cannot_open_backup(): void
    {
        app(BackupService::class)->run();
        $file = Storage::disk('local')->path(Storage::disk('local')->files('backups')[0]);
        $this->expectException(\RuntimeException::class);
        BackupCrypto::decryptFile($file, $file.'.out', base64_decode(BackupCrypto::newKey()));
    }

    public function test_retention_keeps_7_daily_4_weekly_3_monthly(): void
    {
        $dest = new LocalDestination;
        $tmp = tempnam(sys_get_temp_dir(), 'b');
        $day = now()->setTime(2, 0);
        for ($i = 0; $i < 120; $i++) {
            $dest->put($tmp, 'crm-backup-'.$day->copy()->subDays($i)->format('Y-m-d-Hi').'.enc');
        }
        (new BackupService($dest))->applyRetention();
        $left = $dest->list();
        $this->assertGreaterThanOrEqual(10, count($left));
        $this->assertLessThanOrEqual(14, count($left));
        foreach (range(0, 6) as $i) {
            $this->assertContains('crm-backup-'.$day->copy()->subDays($i)->format('Y-m-d-Hi').'.enc', $left);
        }
        $this->assertNotContains('crm-backup-'.$day->copy()->subDays(119)->format('Y-m-d-Hi').'.enc', $left);
    }

    public function test_failure_sends_email(): void
    {
        Mail::fake();
        config(['backup.encryption_key' => null]);
        $run = app(BackupService::class)->run();
        $this->assertSame('failed', $run->status);
        $this->assertSame(1, BackupRun::where('status', 'failed')->count());
        Mail::assertSentCount(1);
    }

    public function test_restore_test_refuses_live_database(): void
    {
        config(['backup.restore_connection' => config('database.default')]);
        app(BackupService::class)->run();
        $this->assertSame('failed', app(BackupService::class)->restoreTest()->status);
    }

    public function test_backups_page_is_super_admin_only(): void
    {
        $this->actingAs($this->lead1)->get(route('admin.backups'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.backups'))->assertOk();
    }
}
