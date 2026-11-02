@extends('layouts.app')
@section('title', 'Follow-ups')
@section('content')
<div class="page-head"><div><h1>Follow-ups</h1><p class="sub">Who to call, and when.</p></div></div>
<div class="tabs">
  <a href="?tab=today" @class(['on' => $tab === 'today'])>Today ({{ $counts['today'] }})</a>
  <a href="?tab=overdue" @class(['on' => $tab === 'overdue'])>Overdue ({{ $counts['overdue'] }})</a>
  <a href="?tab=upcoming" @class(['on' => $tab === 'upcoming'])>Next 7 days ({{ $counts['upcoming'] }})</a>
  <a href="?tab=attention" @class(['on' => $tab === 'attention'])>Needs attention</a>
</div>
@if ($tab === 'attention')<p class="muted small">Open leads with no follow-up date, never called, or not called for 7 days or more.</p>@endif
@include('partials.student-table')
@endsection
