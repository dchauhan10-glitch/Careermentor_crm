@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}</h1>
<div class="tiles">
  @foreach ($tiles as $label => $n)
    <a class="tile {{ $label === 'Overdue follow-ups' && $n ? 'alert' : '' }}" href="{{ match($label) {
        'Follow-ups today' => route('follow-ups', ['tab' => 'today']),
        'Overdue follow-ups' => route('follow-ups', ['tab' => 'overdue']),
        default => route('students.index') } }}"><b>{{ number_format($n) }}</b><span>{{ $label }}</span></a>
  @endforeach
  @isset($unassigned)
    <a class="tile" href="{{ route('students.index', ['assigned' => 'unassigned']) }}"><b>{{ number_format($unassigned) }}</b><span>Unassigned leads</span></a>
    <a class="tile" href="{{ route('admin.duplicates') }}"><b>{{ number_format($openDuplicates) }}</b><span>Possible duplicates</span></a>
  @endisset
</div>

<div class="grid side">
  <div class="card">
    <h2>Calls due today and overdue</h2>
    @forelse ($todayList as $s)
      <div style="padding:6px 0;border-bottom:1px solid var(--line)">
        <a href="{{ route('students.show', $s) }}"><b>{{ $s->name }}</b></a> · {{ $s->status?->name }}
        · @include('partials.follow', ['at' => $s->next_follow_up_at])
      </div>
    @empty
      <p class="muted">Nothing due. Well done.</p>
    @endforelse
    <p><a href="{{ route('follow-ups') }}">All follow-ups</a></p>
  </div>
  <div class="card">
    <h2>Leads by status</h2>
    <table>@foreach ($byStatus as $name => $n)<tr><td>{{ $name }}</td><td style="text-align:right">{{ number_format($n) }}</td></tr>@endforeach</table>
  </div>
</div>

@isset($activity)
<div class="card">
  <h2>Activity by person</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Person</th><th>Team</th><th>Leads assigned</th><th>Calls in last 7 days</th><th>Overdue follow-ups</th></tr></thead>
    @foreach ($activity as $a)
      <tr><td><a href="{{ route('students.index', ['assigned' => $a['user']->id]) }}">{{ $a['user']->name }}</a></td>
        <td>{{ $a['user']->team?->name }}</td><td>{{ $a['assigned'] }}</td><td>{{ $a['calls7'] }}</td>
        <td @class(['due' => $a['overdue'] > 0])>{{ $a['overdue'] }}</td></tr>
    @endforeach
  </table></div>
</div>
@endisset

@isset($bySource)
<div class="card">
  <h2>Enquiries by source</h2>
  <table>@foreach ($bySource as $name => $n)<tr><td>{{ $name }}</td><td style="text-align:right">{{ number_format($n) }}</td></tr>@endforeach</table>
</div>
@endisset

<div class="card">
  <h2>Recent calls</h2>
  @forelse ($recentCalls as $c)
    <div class="small" style="padding:4px 0">{{ $c->called_at->format('j M, g:i A') }} · {{ $c->user->name }} called
      <a href="{{ route('students.show', $c->student_id) }}">{{ $c->student?->name }}</a>: {{ $c->outcome }}</div>
  @empty
    <p class="muted">No calls yet.</p>
  @endforelse
</div>
@endsection
