<?php

namespace App\Http\Controllers\Admin;

use App\Backup\BackupService;
use App\Backup\GoogleDriveDestination;
use App\Models\AuditLog;
use App\Models\BackupRun;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class BackupController extends Controller
{
    public function index()
    {
        return view('admin.backups', [
            'runs' => BackupRun::latest()->limit(40)->get(),
            'destination' => config('backup.destination'),
            'driveConnected' => (bool) Setting::get('google_drive_refresh_token'),
            'googleConfigured' => config('backup.google.client_id') && config('backup.google.client_secret'),
            'keySet' => (bool) config('backup.encryption_key'),
            'alertEmail' => config('backup.alert_email'),
        ]);
    }

    public function runNow(BackupService $backups)
    {
        $run = $backups->run();
        AuditLog::record('backup.manual', $run);

        return back()->with('status', $run->status === 'success' ? 'Backup made: '.$run->message : 'Backup FAILED: '.$run->message);
    }

    /** Step 1 of connecting Google Drive: send the Super Admin to Google's sign-in. */
    public function connectGoogle(Request $request)
    {
        abort_unless(config('backup.google.client_id'), 422, 'Google Drive client details are not set up yet.');
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('backup.google.client_id'),
            'redirect_uri' => route('admin.backups.google.callback'),
            'response_type' => 'code',
            'scope' => GoogleDriveDestination::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]));
    }

    /** Step 2: Google sends the Super Admin back here. */
    public function googleCallback(Request $request)
    {
        $expected = $request->session()->pull('google_oauth_state');
        abort_unless($expected && hash_equals($expected, (string) $request->query('state')), 403);
        if ($request->query('error')) {
            return redirect()->route('admin.backups')->withErrors(['google' => 'Google sign-in was cancelled.']);
        }

        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $request->query('code'),
            'client_id' => config('backup.google.client_id'),
            'client_secret' => config('backup.google.client_secret'),
            'redirect_uri' => route('admin.backups.google.callback'),
            'grant_type' => 'authorization_code',
        ]);
        $refresh = $res->json('refresh_token');
        if (! $res->ok() || ! $refresh) {
            return redirect()->route('admin.backups')->withErrors(['google' => 'Google did not complete the connection. Please try again.']);
        }

        Setting::putSecret('google_drive_refresh_token', $refresh);
        Setting::put('google_drive_folder_id', null);
        AuditLog::record('backup.google_connected');

        return redirect()->route('admin.backups')->with('status', 'Google Drive connected. Set BACKUP_DESTINATION=google_drive to send backups there.');
    }
}
