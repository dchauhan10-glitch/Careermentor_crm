@extends('layouts.app')
@section('title', 'Websites')
@section('content')
<h1>Connected websites</h1>

@if (session('new_key'))
  <div class="card" style="border-color:var(--warm);background:#fffaf0">
    <h2>New key for {{ session('new_key.name') }}</h2>
    <p><b>Copy it now. It will not be shown again.</b> Send it to the website's developer privately (not by WhatsApp group or email to many people). It must be kept only on the website's server, never in the web page.</p>
    <input readonly value="{{ session('new_key.key') }}" onclick="this.select()" style="font-family:monospace">
  </div>
@endif

<div class="table-wrap" style="margin-bottom:16px"><table>
  <thead><tr><th>Website</th><th>Shows in CRM as</th><th>Key starts with</th><th>Status</th><th>Sign-ups (30 days / all)</th><th>Last sign-up</th><th>Limit per minute</th><th>Actions</th></tr></thead>
  @forelse ($connections as $c)
    <tr>
      <td><b>{{ $c->name }}</b></td>
      <td>{{ $c->source->name }}</td>
      <td><code>{{ $c->key_prefix }}…</code></td>
      <td>@if ($c->is_active)<span class="pill p-cold">On</span>@else<span class="pill p-hot">Off</span>@endif</td>
      <td>{{ $last30[$c->id] ?? 0 }} / {{ $counts[$c->id] ?? 0 }}</td>
      <td class="small">{{ $c->last_used_at?->format('j M Y, g:i A') ?? 'Never' }}</td>
      <td><form method="post" action="{{ route('admin.websites.update', $c) }}" class="row">@csrf @method('put')<input name="per_minute_limit" type="number" value="{{ $c->per_minute_limit }}" style="max-width:80px"><button class="btn small light" style="flex:0">Save</button></form></td>
      <td>
        <form method="post" action="{{ route('admin.websites.toggle', $c) }}">@csrf<button class="btn small {{ $c->is_active ? 'danger' : 'light' }}">{{ $c->is_active ? 'Switch off' : 'Switch on' }}</button></form>
        <form method="post" action="{{ route('admin.websites.key', $c) }}" style="margin-top:4px" onsubmit="return confirm('The old key will stop working immediately. Continue?')">@csrf<button class="btn small light">Replace key</button></form>
      </td>
    </tr>
  @empty
    <tr><td colspan="8" class="muted">No websites connected yet.</td></tr>
  @endforelse
</table></div>

<div class="card">
  <h2>Connect a new website</h2>
  <form method="post" action="{{ route('admin.websites.store') }}">
    @csrf
    <div class="row">
      <div><label>Website name</label><input name="name" placeholder="e.g. Collegioz" required></div>
      <div><label>Source name shown on leads</label><input name="source_name" list="ws" placeholder="e.g. Collegioz website" required>
        <datalist id="ws">@foreach ($websiteSources as $s)<option value="{{ $s->name }}">@endforeach</datalist></div>
      <div><label>Sign-ups allowed per minute</label><input name="per_minute_limit" type="number" value="30" required></div>
    </div>
    <p><button class="btn">Create website and key</button></p>
  </form>
  <details><summary>Instructions for the website's developer</summary>
    <div class="small">
      <p>Send each sign-up from the website's <b>server</b> (never from browser JavaScript) to:</p>
      <p><code>POST {{ url('/api/v1/leads') }}</code><br>Header: <code>Authorization: Bearer &lt;key&gt;</code>, <code>Content-Type: application/json</code></p>
      <p>Fields: <code>name</code>*, <code>mobile</code>*, <code>email</code>, <code>course</code>, <code>exam</code>, <code>city_state</code>, <code>parent_mobile</code>, <code>source_page</code> (the page address), <code>consent</code>* (true when the tick box was ticked), <code>hp</code> (a hidden form field that people never fill; pass it on as received). Optional header <code>X-Visitor-Ip</code> with the student's IP address for extra spam protection.</p>
      <p>Replies: 201 received · 422 a field is wrong (message says which) · 401 key wrong or switched off · 429 too many sign-ups, try again in a minute.</p>
    </div>
  </details>
</div>
@endsection
