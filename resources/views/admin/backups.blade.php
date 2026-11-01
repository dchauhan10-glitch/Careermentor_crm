@extends('layouts.app')
@section('title', 'Backups')
@section('content')
<h1>Backups</h1>
<div class="grid g2">
  <div class="card">
    <h2>Set-up</h2>
    <dl class="facts">
      <dt>Encryption key</dt><dd>{!! $keySet ? 'Set' : '<span class="due">Not set</span>' !!}</dd>
      <dt>Backups go to</dt><dd>{{ $destination === 'google_drive' ? 'Google Drive' : 'Server private folder (testing only)' }}</dd>
      <dt>Google Drive</dt><dd>{{ $driveConnected ? 'Connected' : 'Not connected' }}</dd>
      <dt>Failure emails to</dt><dd>{!! $alertEmail ?: '<span class="due">Not set</span>' !!}</dd>
      <dt>Schedule</dt><dd>Every night at 2:00 AM. Restore test on the 1st of every month.</dd>
      <dt>Copies kept</dt><dd>7 daily, 4 weekly, 3 monthly</dd>
    </dl>
    <p>
      <form method="post" action="{{ route('admin.backups.run') }}" style="display:inline">@csrf<button class="btn">Back up now</button></form>
      @if ($googleConfigured)<a class="btn light" href="{{ route('admin.backups.google') }}">{{ $driveConnected ? 'Reconnect' : 'Connect' }} Google Drive</a>@endif
    </p>
  </div>
  <div class="card">
    <h2>If you ever need to restore</h2>
    <ol class="small">
      <li>Download the newest <code>crm-backup-….enc</code> file from the Google Drive folder.</li>
      <li>Have your encryption key ready (it is not stored in Drive).</li>
      <li>Ask your developer (or Claude) to run the restore steps. The live database is only replaced after you confirm.</li>
    </ol>
    <p class="small muted">The monthly restore test does this automatically into a separate test database, so you know the backups really work.</p>
  </div>
</div>
<h2>History</h2>
<div class="table-wrap"><table>
  <thead><tr><th>When</th><th>Type</th><th>Result</th><th>File</th><th>Size</th><th>Details</th></tr></thead>
  @forelse ($runs as $r)
    <tr class="small"><td>{{ $r->created_at->format('j M Y, g:i A') }}</td><td>{{ $r->kind === 'backup' ? 'Backup' : 'Restore test' }}</td>
      <td>@if ($r->status === 'success')<span class="pill p-cold">OK</span>@else<span class="pill p-hot">Failed</span>@endif</td>
      <td>{{ $r->file_name }}</td><td>{{ $r->size_bytes ? number_format($r->size_bytes / 1048576, 2).' MB' : '' }}</td><td>{{ $r->message }}</td></tr>
  @empty
    <tr><td colspan="6" class="muted">No backups yet.</td></tr>
  @endforelse
</table></div>
@endsection
