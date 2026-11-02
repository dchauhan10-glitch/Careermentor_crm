@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php($look = [
  'Total leads' => ['student', ''],
  'New' => ['star', 'yellow'],
  'Interested' => ['chat', 'blue'],
  'Converted' => ['check', ''],
  'Follow-ups today' => ['calendar', 'yellow'],
  'Overdue follow-ups' => ['alert', 'alert'],
])
<section class="hero">
  <div>
    <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }}</h1>
    <p>{{ now()->format('l, j F Y') }} · {{ number_format($tiles['Follow-ups today']) }} follow-ups today
      @if ($tiles['Overdue follow-ups']) · {{ number_format($tiles['Overdue follow-ups']) }} overdue @endif</p>
  </div>
  <div class="actions">
    <a class="btn accent" href="{{ route('follow-ups', ['tab' => 'today']) }}">@include('partials.icon', ['name' => 'phone'])Start calling</a>
    @can('create', App\Models\Student::class)
      <a class="btn ghost" href="{{ route('students.create') }}">@include('partials.icon', ['name' => 'plus'])Add student</a>
    @endcan
  </div>
</section>

<div class="tiles">
  @foreach ($tiles as $label => $n)
    @php([$icon, $tone] = $look[$label] ?? ['student', ''])
    <a @class(['tile', $tone => $tone && ($tone !== 'alert' || $n)]) href="{{ match($label) {
        'Follow-ups today' => route('follow-ups', ['tab' => 'today']),
        'Overdue follow-ups' => route('follow-ups', ['tab' => 'overdue']),
        default => route('students.index') } }}">
      <span class="ic">@include('partials.icon', ['name' => $icon])</span>
      <span><b>{{ number_format($n) }}</b><span>{{ $label }}</span></span>
    </a>
  @endforeach
  @isset($unassigned)
    <a class="tile yellow" href="{{ route('students.index', ['assigned' => 'unassigned']) }}">
      <span class="ic">@include('partials.icon', ['name' => 'inbox'])</span>
      <span><b>{{ number_format($unassigned) }}</b><span>Unassigned leads</span></span></a>
    <a class="tile blue" href="{{ route('admin.duplicates') }}">
      <span class="ic">@include('partials.icon', ['name' => 'copy'])</span>
      <span><b>{{ number_format($openDuplicates) }}</b><span>Possible duplicates</span></span></a>
  @endisset
</div>

<div class="grid split">
  <div class="card">
    <div class="card-head"><h2>Calls due today and overdue</h2><a href="{{ route('follow-ups') }}">All follow-ups →</a></div>
    @forelse ($todayList as $s)
      <div class="list-row">
        <span class="avatar">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</span>
        <div class="grow"><a href="{{ route('students.show', $s) }}">{{ $s->name }}</a>
          <div class="small muted">@include('partials.follow', ['at' => $s->next_follow_up_at])</div></div>
        @if ($s->status)<span class="pill status">{{ $s->status->name }}</span>@endif
        <a class="btn small light" href="tel:+91{{ $s->mobile }}" title="Call {{ $s->name }}">@include('partials.icon', ['name' => 'phone'])</a>
      </div>
    @empty
      <p class="empty">Nothing due. Well done.</p>
    @endforelse
  </div>
  <div class="card">
    <h2>Leads by status</h2>
    @php($max = max(1, $byStatus->max() ?? 1))
    <ul class="bars">
      @foreach ($byStatus as $name => $n)
        <li><span>{{ $name }}</span><span class="track"><span class="fill" style="display:block;width:{{ round($n / $max * 100) }}%"></span></span><b>{{ number_format($n) }}</b></li>
      @endforeach
    </ul>
  </div>
</div>

@isset($activity)
<div class="card">
  <h2>Activity by person</h2>
  <div class="table-wrap" style="box-shadow:none"><table>
    <thead><tr><th>Person</th><th>Team</th><th>Leads assigned</th><th>Calls in last 7 days</th><th>Overdue follow-ups</th></tr></thead>
    <tbody>
    @foreach ($activity as $a)
      <tr><td><a href="{{ route('students.index', ['assigned' => $a['user']->id]) }}"><b>{{ $a['user']->name }}</b></a></td>
        <td>{{ $a['user']->team?->name }}</td><td>{{ $a['assigned'] }}</td><td>{{ $a['calls7'] }}</td>
        <td>@if ($a['overdue'])<span class="pill p-hot">{{ $a['overdue'] }}</span>@else<span class="muted">0</span>@endif</td></tr>
    @endforeach
    </tbody>
  </table></div>
</div>
@endisset

<div class="grid g2">
  @isset($bySource)
  <div class="card">
    <h2>Enquiries by source</h2>
    @php($maxS = max(1, $bySource->max() ?? 1))
    <ul class="bars">
      @foreach ($bySource as $name => $n)
        <li><span>{{ $name }}</span><span class="track"><span class="fill" style="display:block;width:{{ round($n / $maxS * 100) }}%"></span></span><b>{{ number_format($n) }}</b></li>
      @endforeach
    </ul>
  </div>
  @endisset

  <div class="card">
    <h2>Recent calls</h2>
    <ul class="timeline">
    @forelse ($recentCalls as $c)
      <li><div class="meta">{{ $c->called_at->format('j M, g:i A') }} · {{ $c->user->name }}</div>
        <a href="{{ route('students.show', $c->student_id) }}"><b>{{ $c->student?->name }}</b></a>: {{ $c->outcome }}</li>
    @empty
      <p class="empty">No calls yet.</p>
    @endforelse
    </ul>
  </div>
</div>
@endsection
