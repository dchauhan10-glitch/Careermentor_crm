@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
  // One colour per lead status, used by the chart, its legend and the status badges, so they always match.
  $statusPalette = [
    'New' => '#8B5CF6', 'Not Contacted' => '#64748B', 'Interested' => '#06B6D4', 'Not Interested' => '#EF4444',
    'Call Back' => '#F59E0B', 'No Response' => '#F97316', 'Follow-up Required' => '#2563EB',
    'Converted' => '#10B981', 'Closed' => '#94A3B8',
  ];
  $spare = ['#172554', '#0EA5E9', '#D946EF', '#84CC16', '#A16207'];
  $statusColor = function (string $name) use (&$statusPalette, $spare) {
      return $statusPalette[$name] ??= $spare[count($statusPalette) % count($spare)];
  };
  $h = now()->hour;
  $overdue = $tiles['Overdue follow-ups'];
  $today = $tiles['Follow-ups today'];
  $unassignedCount = $unassigned ?? 0;
  // [label, value, icon, tone, link, emphasis]
  $cards = [
    ['Total leads', $tiles['Total leads'], 'student', 'blue', route('students.index'), false],
    ['New leads', $tiles['New'], 'star', 'purple', route('students.index'), false],
    ['Interested', $tiles['Interested'], 'chat', 'cyan', route('students.index'), false],
    ['Converted', $tiles['Converted'], 'check', 'green', route('students.index'), false],
    ['Follow-ups today', $today, 'calendar', 'amber', route('follow-ups', ['tab' => 'today']), false],
    ['Overdue follow-ups', $overdue, 'alert', 'coral', route('follow-ups', ['tab' => 'overdue']), $overdue > 0],
  ];
  if (isset($unassigned)) {
      $cards[] = ['Unassigned leads', $unassigned, 'inbox', 'orange', route('students.index', ['assigned' => 'unassigned']), $unassigned > 0];
      $cards[] = ['Possible duplicates', $openDuplicates, 'copy', 'slate', route('admin.duplicates'), false];
  }
@endphp
<div class="dash">

<header class="dash-head">
  <div>
    <h1>Good {{ $h < 12 ? 'morning' : ($h < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }}</h1>
    <p class="sub"><span data-date>{{ now()->format('l, j F Y') }}</span>
      <span class="dot-sep"></span>{{ number_format($today) }} follow-ups due today
      <span class="dot-sep"></span>{{ number_format($tiles['Total leads']) }} leads in your view</p>
  </div>
  <div class="dash-actions">
    <a class="btn-x primary" href="{{ route('follow-ups', ['tab' => 'today']) }}">@include('partials.icon', ['name' => 'phone'])Start calling</a>
    @can('create', App\Models\Student::class)
      <a class="btn-x outline" href="{{ route('students.create') }}">@include('partials.icon', ['name' => 'plus'])Add student</a>
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
  @foreach ($cards as [$label, $n, $icon, $tone, $href, $strong])
    <a href="{{ $href }}" @class(['kpi', 't-'.$tone, 'strong' => $strong])>
      <span class="kpi-ic">@include('partials.icon', ['name' => $icon])</span>
      <span class="kpi-lbl">{{ $label }}</span>
      <b class="kpi-n">{{ number_format($n) }}</b>
      @if ($strong)<span class="kpi-flag">Needs attention</span>@endif
    </a>
  @endforeach
</section>

<div class="dash-grid">
  <section class="panel">
    <div class="panel-head">
      <div><h2>Calls due today and overdue</h2><p>Oldest first. Tap the phone to call.</p></div>
      <a class="link" href="{{ route('follow-ups') }}">All follow-ups →</a>
    </div>
    <ul class="calls">
      @forelse ($todayList as $s)
        @php $late = $s->next_follow_up_at && $s->next_follow_up_at->lt(today()); @endphp
        <li @class(['late' => $late])>
          <span class="who-av" style="--c:{{ $statusColor($s->status?->name ?? '') }}">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</span>
          <div class="call-main">
            <a href="{{ route('students.show', $s) }}">{{ $s->name }}</a>
            <span class="when">
              @if ($late)<span class="tag-late">Overdue</span>@else<span class="tag-today">Today</span>@endif
              {{ $s->next_follow_up_at?->isToday() ? $s->next_follow_up_at->format('g:i A') : $s->next_follow_up_at?->format('j M Y, g:i A') }}
            </span>
          </div>
          @if ($s->status)
            <span class="badge" style="--c:{{ $statusColor($s->status->name) }}">{{ $s->status->name }}</span>
          @endif
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
        <td class="num">@if ($a['overdue'])<span class="badge" style="--c:#EF4444">{{ $a['overdue'] }}</span>@else<span class="muted">0</span>@endif</td>
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
    @php $srcColors = ['#2563EB', '#8B5CF6', '#06B6D4', '#10B981', '#F59E0B', '#EF4444', '#64748B']; @endphp
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
