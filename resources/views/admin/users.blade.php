@extends('layouts.app')
@section('title', 'Staff and teams')
@section('content')
<h1>Staff and teams</h1>

<div class="grid g2">
@foreach ($teams as $team)
  <div class="card">
    <h2>{{ $team->name }} <span class="small muted">Team Lead: {{ $team->lead?->name }}</span></h2>
    @php($counsellors = $team->members->where('role', \App\Support\Role::Counsellor))
    @forelse ($counsellors as $m)
      <div class="row small" style="align-items:center;border-bottom:1px solid var(--line);padding:4px 0">
        <div>{{ $m->name }} @unless ($m->is_active)<span class="pill">Disabled</span>@endunless</div>
        <form method="post" action="{{ route('admin.users.team', $m) }}" class="row" style="flex:2">
          @csrf
          <select name="team_id" aria-label="Move to team">@foreach ($teams as $t)<option value="{{ $t->id }}" @selected($t->id === $team->id)>{{ $t->name }}</option>@endforeach</select>
          <button class="btn small light" style="flex:0">Move</button>
        </form>
      </div>
    @empty
      <p class="muted small">No Counsellors yet.</p>
    @endforelse
  </div>
@endforeach
</div>

<div class="card">
  <h2>Add a staff member</h2>
  <form method="post" action="{{ route('admin.users.store') }}">
    @csrf
    <div class="row">
      <div><label>Name</label><input name="name" value="{{ old('name') }}" required></div>
      <div><label>Email (used to log in)</label><input name="email" type="email" value="{{ old('email') }}" required></div>
      <div><label>Role</label><select name="role"><option value="counsellor">Counsellor</option><option value="team_lead">Team Lead (gets a new team)</option></select></div>
    </div>
    <div class="row">
      <div><label>Team (for a Counsellor)</label><select name="team_id"><option value=""></option>@foreach ($teams as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->lead?->name }})</option>@endforeach</select></div>
      <div><label>New team name (for a Team Lead)</label><input name="team_name"></div>
      <div><label>First password (min. 10 letters and numbers)</label><input name="password" type="text" autocomplete="new-password" required></div>
    </div>
    <p><button class="btn">Add</button></p>
  </form>
</div>

<div class="card">
  <h2>All accounts</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Team</th><th>Last login</th><th>Actions</th></tr></thead>
    @foreach ($users as $u)
      <tr>
        <td>{{ $u->name }} @unless ($u->is_active)<span class="pill">Disabled</span>@endunless</td>
        <td>{{ $u->email }}</td><td>{{ $u->role->label() }}</td><td>{{ $u->team?->name }}</td>
        <td class="small">{{ $u->last_login_at?->format('j M, g:i A') ?? 'Never' }}</td>
        <td>
          @unless ($u->isSuperAdmin())
          <details><summary class="small">Manage</summary>
            <form method="post" action="{{ route('admin.users.toggle', $u) }}">@csrf<button class="btn small {{ $u->is_active ? 'danger' : 'light' }}">{{ $u->is_active ? 'Disable account' : 'Enable account' }}</button></form>
            <form method="post" action="{{ route('admin.users.password', $u) }}" class="row" style="margin-top:6px">@csrf<input name="password" type="text" placeholder="New password" autocomplete="new-password"><button class="btn small light" style="flex:0">Set</button></form>
            <form method="post" action="{{ route('admin.users.handover', $u) }}" class="row" style="margin-top:6px">@csrf
              <select name="to_user_id" aria-label="Hand over leads to">@foreach ($users->where('is_active', true)->where('id', '!=', $u->id)->reject(fn ($x) => $x->isSuperAdmin()) as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select>
              <button class="btn small light" style="flex:0">Hand over all leads</button></form>
          </details>
          @endunless
        </td>
      </tr>
    @endforeach
  </table></div>
</div>
@endsection
