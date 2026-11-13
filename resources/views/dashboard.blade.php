@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
  $statusColor = fn (string $name) => \App\Support\StatusTone::hex($name);
  $h = now()->hour;
  $overdue = $tiles['Overdue follow-ups'];
  $today = $tiles['Follow-ups today'];
  $unassignedCount = $unassigned ?? 0;
  $me = auth()->user();
  $scope = $me->isSuperAdmin() ? 'All teams' : ($me->isTeamLead() ? 'Your team' : 'Your leads');
  // [label, value, icon, tone, link, foot]
  $cards = [
    ['Total leads', $tiles['Total leads'], 'student', 'hero', route('students.index'), $scope],
    ['Calls made today', $tiles['Calls today'], 'phone', 'gold', route('calls.index', ['period' => 'today']), $me->isCounsellor() ? 'By you' : 'By '.mb_strtolower($scope)],
    ['Interested', $tiles['Interested'], 'trend', 'green', route('students.index', ['quick' => 'interested']), 'Warm leads'],
    ['Not Interested', $tiles['Not Interested'], 'x-circle', 'red', route('students.index', ['quick' => 'not_interested']), 'Closed for now'],
    ['Follow-ups due today', $today, 'calendar', 'amber', route('follow-ups', ['tab' => 'today']), $overdue ? number_format($overdue).' overdue' : 'None overdue'],
  ];
  // Smaller second row: [label, value, icon, tone, link, flag]
  $minor = [
    ['Overdue', $overdue, 'alert', 'amber', route('follow-ups', ['tab' => 'overdue']), $overdue > 0],
    ['Not called yet', $tiles['Not called yet'], 'inbox', 'grey', route('students.index', ['quick' => 'not_called']), false],
    ['Converted', $tiles['Converted'], 'check', 'green', route('students.index', ['quick' => 'converted']), false],
  ];
  if (isset($unassigned)) {
      $minor[] = ['Unassigned leads', $unassigned, 'users', 'gold', route('students.index', ['assigned' => 'unassigned']), $unassigned > 0];
      $minor[] = ['Possible duplicates', $openDuplicates, 'copy', 'grey', route('admin.duplicates'), false];
  }
@endphp
<div class="dash">

<header class="dash-head">
  <div>
    <h1>Good {{ $h < 12 ? 'morning' : ($h < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', $me->name)[0] }}</h1>
    <p class="sub"><span data-date>{{ now()->format('l, j F Y') }}</span>
      <span class="dot-sep"></span><span>{{ $scope }}</span></p>
  </div>
  <div class="dash-actions">
    <a class="btn" href="{{ route('follow-ups', ['tab' => 'today']) }}">@include('partials.icon', ['name' => 'phone'])Start calling</a>
    @can('create', App\Models\Student::class)
      <a class="btn light" href="{{ route('students.create') }}">@include('partials.icon', ['name' => 'plus'])Add lead</a>
    @endcan
  </div>
</header>

@if ($overdue || $unassignedCount)
  <div class="attention" role="status">
    <span class="att-ic">@include('partials.icon', ['name' => 'alert'])</span>
    <p>
      @if ($overdue)<a href="{{ route('follow-ups', ['tab' => 'overdue']) }}"><b>{{ number_format($overdue) }} overdue follow-ups</b></a>@endif
      @if ($overdue && $unassignedCount) and @endif
      @if ($unassignedCount)<a href="{{ route('students.index', ['assigned' => 'unassigned']) }}"><b>{{ number_format($unassignedCount) }} unassigned leads</b></a>@endif
      {{ ($overdue && $unassignedCount) || $overdue > 1 || $unassignedCount > 1 ? 'need' : 'needs' }} your attention.
    </p>
    <a class="att-go" href="{{ route('follow-ups', ['tab' => $overdue ? 'overdue' : 'today']) }}">Review now →</a>
  </div>
@endif

<section class="kpis" aria-label="Summary">
  @foreach ($cards as [$label, $n, $icon, $tone, $href, $foot])
    <a href="{{ $href }}" @class(['kpi', $tone === 'hero' ? 'hero' : 't-'.$tone])>
      <span class="kpi-top"><span class="kpi-lbl">{{ $label }}</span><span class="kpi-ic">@include('partials.icon', ['name' => $icon])</span></span>
      <b class="kpi-n">{{ number_format($n) }}</b>
      <span class="kpi-foot">{{ $foot }}</span>
    </a>
  @endforeach
</section>

<section class="kpis minor" aria-label="More numbers">
  @foreach ($minor as [$label, $n, $icon, $tone, $href, $strong])
    <a href="{{ $href }}" @class(['kpi', 't-'.$tone])>
      <span class="kpi-ic">@include('partials.icon', ['name' => $icon])</span>
      <span class="flex flex-col min-w-0"><span class="kpi-lbl truncate">{{ $label }}</span><b class="kpi-n">{{ number_format($n) }}</b></span>
      @if ($strong)<span class="ml-auto w-2 h-2 rounded-full bg-amber-500" title="Needs attention"></span>@endif
    </a>
  @endforeach
</section>

<div class="dash-grid">
  <section class="panel">
    <div class="panel-head">
      <div><h2>Calls due today and overdue</h2><p>Oldest first. Tap the green phone to call.</p></div>
      <a class="link" href="{{ route('follow-ups') }}">All follow-ups →</a>
    </div>
    <ul class="calls">
      @forelse ($todayList as $s)
        @php $late = $s->next_follow_up_at && $s->next_follow_up_at->lt(today()); @endphp
        <li @class(['late' => $late])>
          <span class="who-av">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</span>
          <div class="call-main">
            <a href="{{ route('students.show', $s) }}">{{ $s->name }}</a>
            <span class="when">
              @if ($late)<span class="tag-late">Overdue</span>@else<span class="tag-today">Today</span>@endif
              {{ $s->next_follow_up_at?->isToday() ? $s->next_follow_up_at->format('g:i A') : $s->next_follow_up_at?->format('j M Y, g:i A') }}
            </span>
          </div>
          <span class="hidden sm:inline-flex">@include('partials.status', ['s' => $s->status])</span>
          <a class="call-btn" href="tel:+91{{ $s->mobile }}" title="Call {{ $s->name }}" aria-label="Call {{ $s->name }}">@include('partials.icon', ['name' => 'phone'])</a>
        </li>
      @empty
        <li class="calls-empty">@include('partials.icon', ['name' => 'check'])<span>Nothing due. Well done.</span></li>
      @endforelse
    </ul>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div><h2>Leads by status</h2><p>Hover a slice for details.</p></div>
      <a class="link" href="{{ route('students.index') }}">View all →</a>
    </div>
    @php
      $sum = $byStatus->sum();
      $r = 64; $circ = 2 * M_PI * $r; $offset = 0;
    @endphp
    <div class="chart">
      <div class="donut-box">
        <svg viewBox="0 0 160 160" class="donut-svg" role="img" aria-label="Leads by status chart">
          <circle cx="80" cy="80" r="{{ $r }}" class="donut-track"/>
          @if ($sum)
            @foreach ($byStatus as $name => $n)
              @php $len = $n / $sum * $circ; @endphp
              <circle cx="80" cy="80" r="{{ $r }}" class="seg" data-i="{{ $loop->index }}"
                data-label="{{ $name }}" data-n="{{ number_format($n) }}" data-pct="{{ round($n / $sum * 100) }}"
                stroke="{{ $statusColor($name) }}"
                stroke-dasharray="{{ max(0, round($len - ($n < $sum ? 1.5 : 0), 2)) }} {{ round($circ, 2) }}"
                stroke-dashoffset="{{ round(-$offset, 2) }}"/>
              @php $offset += $len; @endphp
            @endforeach
          @endif
        </svg>
        <div class="donut-center"><b>{{ number_format($sum) }}</b><span>total leads</span></div>
        <div class="tip" hidden></div>
      </div>
      <ul class="legend-x">
        @foreach ($byStatus as $name => $n)
          <li data-i="{{ $loop->index }}"><i style="background:{{ $statusColor($name) }}"></i><span>{{ $name }}</span>
            <b>{{ number_format($n) }}</b><em>{{ $sum ? round($n / $sum * 100) : 0 }}%</em></li>
        @endforeach
      </ul>
    </div>
  </section>
</div>

@isset($activity)
<section class="panel">
  <div class="panel-head"><div><h2>Activity by person</h2><p>Calls made in the last 7 days and follow-ups that are late.</p></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Person</th><th>Team</th><th class="num">Leads assigned</th><th class="num">Calls in last 7 days</th><th class="num">Overdue follow-ups</th></tr></thead>
    <tbody>
    @foreach ($activity as $a)
      <tr>
        <td><a class="person" href="{{ route('students.index', ['assigned' => $a['user']->id]) }}"><span class="mini-av">{{ mb_strtoupper(mb_substr($a['user']->name, 0, 1)) }}</span>{{ $a['user']->name }}</a></td>
        <td class="muted">{{ $a['user']->team?->name }}</td>
        <td class="num">{{ $a['assigned'] }}</td>
        <td class="num">{{ $a['calls7'] }}</td>
        <td class="num">@if ($a['overdue'])<span class="badge st-amber">{{ $a['overdue'] }}</span>@else<span class="muted">0</span>@endif</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@endisset

<div class="dash-grid even">
  @isset($bySource)
  <section class="panel">
    <div class="panel-head"><div><h2>Enquiries by source</h2><p>Where your students come from.</p></div></div>
    @php $maxS = max(1, $bySource->max() ?? 1); @endphp
    @php $srcColors = ['#116d44', '#feba0f', '#3f9c72', '#c48700', '#74bd9a', '#ffd24d', '#0b4a2f']; @endphp
    <ul class="src">
      @foreach ($bySource as $name => $n)
        <li><span class="src-name">{{ $name }}</span><b>{{ number_format($n) }}</b>
          <span class="src-track"><span style="width:{{ round($n / $maxS * 100) }}%;background:{{ $srcColors[$loop->index % count($srcColors)] }}"></span></span></li>
      @endforeach
    </ul>
  </section>
  @endisset

  <section class="panel">
    <div class="panel-head"><div><h2>Recent calls</h2><p>The latest notes from the team.</p></div></div>
    <ul class="feed">
    @forelse ($recentCalls as $c)
      <li><span class="feed-dot"></span>
        <div><a href="{{ route('students.show', $c->student_id) }}">{{ $c->student?->name }}</a>
          <p>{{ $c->outcome }}</p>
          <span class="feed-meta">{{ $c->called_at->format('j M, g:i A') }} · {{ $c->user->name }}</span></div></li>
    @empty
      <li class="calls-empty">@include('partials.icon', ['name' => 'phone'])<span>No calls yet.</span></li>
    @endforelse
    </ul>
  </section>
</div>
</div>

<script>
(function () {
  var box = document.querySelector('.donut-box'); if (!box) return;
  var tip = box.querySelector('.tip'), segs = box.querySelectorAll('.seg'), rows = document.querySelectorAll('.legend-x li');
  function on(i) {
    segs.forEach(function (s) { s.classList.toggle('dim', s.dataset.i !== i); });
    rows.forEach(function (r) { r.classList.toggle('on', r.dataset.i === i); });
  }
  function off() { segs.forEach(function (s) { s.classList.remove('dim'); }); rows.forEach(function (r) { r.classList.remove('on'); }); tip.hidden = true; }
  segs.forEach(function (s) {
    s.addEventListener('mousemove', function (e) {
      var b = box.getBoundingClientRect();
      tip.innerHTML = '<b>' + s.dataset.label + '</b>' + s.dataset.n + ' leads · ' + s.dataset.pct + '%';
      tip.style.left = (e.clientX - b.left) + 'px'; tip.style.top = (e.clientY - b.top) + 'px'; tip.hidden = false; on(s.dataset.i);
    });
    s.addEventListener('mouseleave', off);
  });
  rows.forEach(function (r) { r.addEventListener('mouseenter', function () { on(r.dataset.i); }); r.addEventListener('mouseleave', off); });
})();
</script>
@endsection
