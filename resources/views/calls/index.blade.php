@extends('layouts.app')
@section('title', 'Call History')
@section('content')
<div class="page-head">
  <div><h1>Call History</h1><p class="sub">Every saved call, newest first. Saved calls can't be edited or deleted.</p></div>
</div>

<div class="chips" role="navigation" aria-label="Period">
  @foreach (['today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', 'all' => 'All time'] as $k => $v)
    <a href="{{ request()->fullUrlWithQuery(['period' => $k, 'page' => null]) }}" @class(['chip', 'on' => $filters['period'] === (string) $k])>{{ $v }}</a>
  @endforeach
</div>

<form method="get" class="card !p-4">
  <input type="hidden" name="period" value="{{ $filters['period'] }}">
  <div class="row items-end">
    <div class="!min-w-[220px]"><label class="!mt-0">Student name or mobile</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="e.g. Priya or 98765"></div>
    @if ($callers->isNotEmpty())
      <div><label class="!mt-0">Called by</label><select name="by"><option value="">Anyone</option>
        @foreach ($callers as $c)<option value="{{ $c->id }}" @selected(($filters['by'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    @endif
    <div class="!flex-none flex gap-2"><button class="btn green">@include('partials.icon', ['name' => 'search'])Search</button>
      <a class="btn light" href="{{ route('calls.index') }}">Clear</a></div>
  </div>
</form>

<div class="table-wrap desk-only">
<table>
  <thead><tr><th>Date and time</th><th>Student</th><th>Called by</th><th>Response</th><th>Suggestion given</th><th>Status set</th><th>Next follow-up</th></tr></thead>
  <tbody>
  @forelse ($calls as $c)
    <tr>
      <td class="whitespace-nowrap"><b>{{ $c->called_at->format('j M Y') }}</b><div class="small muted">{{ $c->called_at->format('g:i A') }}</div></td>
      <td><a class="stu-name" href="{{ route('students.show', $c->student_id) }}">{{ $c->student?->name }}</a><div class="small muted">{{ $c->student?->mobile }}</div></td>
      <td class="whitespace-nowrap">{{ $c->user?->name }}</td>
      <td class="min-w-[200px]">{{ $c->outcome }}@if ($c->corrects_call_entry_id) <span class="pill">Correction</span>@endif
        @if ($c->remarks)<div class="small muted">{{ $c->remarks }}</div>@endif</td>
      <td class="min-w-[180px] text-slate-600">{{ $c->advice }}</td>
      <td>@if ($c->status)@include('partials.status', ['s' => $c->status])@else<span class="muted small">No change</span>@endif</td>
      <td class="whitespace-nowrap small">{{ $c->next_follow_up_at?->format('j M Y, g:i A') }}</td>
    </tr>
  @empty
    <tr><td colspan="7"><div class="empty">No calls found for this period.</div></td></tr>
  @endforelse
  </tbody>
</table>
</div>

<ul class="lead-cards">
  @forelse ($calls as $c)
    <li class="lead-card !items-start">
      <div class="main">
        <a href="{{ route('students.show', $c->student_id) }}">{{ $c->student?->name }}</a>
        <div class="sub"><span>{{ $c->called_at->format('j M, g:i A') }}</span><span>· {{ $c->user?->name }}</span>@if ($c->status)@include('partials.status', ['s' => $c->status])@endif</div>
        <p class="m-0 mt-2 text-sm"><b>{{ $c->outcome }}</b></p>
        @if ($c->advice)<p class="m-0 mt-1 text-[13px] text-slate-600">Suggestion: {{ $c->advice }}</p>@endif
      </div>
    </li>
  @empty
    <li class="empty">No calls found for this period.</li>
  @endforelse
</ul>
<div class="pagination">{{ $calls->links('partials.pager', ['noun' => 'calls']) }}</div>
@endsection
