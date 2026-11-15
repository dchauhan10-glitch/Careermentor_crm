@extends('layouts.app')
@section('title', 'Leads')
@section('content')
<div class="page-head">
  <div><h1>Leads</h1><p class="sub">{{ number_format($students->total()) }} found</p></div>
  <div class="actions">
    @can('admin')<a class="btn light" href="{{ route('admin.export', request()->query()) }}" onclick="return confirm('Export this list? Exports are recorded in the activity log.')">@include('partials.icon', ['name' => 'download'])Export list</a>@endcan
    @can('create', App\Models\Student::class)<a class="btn" href="{{ route('students.create') }}">@include('partials.icon', ['name' => 'plus'])Add lead</a>@endcan
  </div>
</div>

<form method="get" class="relative mb-4" role="search">
  @foreach (collect(request()->query())->except(['q', 'page']) as $k => $v)@if (is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
  <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">@include('partials.icon', ['name' => 'search', 'class' => 'w-5 h-5'])</span>
  <input name="q" value="{{ $filters['q'] ?? '' }}" class="!pl-12 !h-12 !rounded-2xl !text-base shadow-card" placeholder="Search by student name or mobile number" aria-label="Search leads" @if (request('focus') === 'search') autofocus @endif>
</form>

@php($tones = ['interested' => 'bg-emerald-500', 'not_interested' => 'bg-red-500', 'not_called' => 'bg-slate-400', 'follow_up_due' => 'bg-amber-500', 'converted' => 'bg-emerald-700'])
<nav class="chips" aria-label="Quick filters">
  <a href="{{ request()->fullUrlWithQuery(['quick' => null, 'page' => null]) }}" @class(['chip', 'on' => empty($filters['quick'])])>All leads</a>
  @foreach (\App\Services\StudentSearch::QUICK as $key => $label)
    <a href="{{ request()->fullUrlWithQuery(['quick' => $key, 'page' => null]) }}" @class(['chip', 'on' => ($filters['quick'] ?? null) === $key])><i class="{{ $tones[$key] }}"></i>{{ $label }}<b>{{ number_format($quickCounts[$key]) }}</b></a>
  @endforeach
</nav>

<details class="card !py-4" @if (collect($filters)->except(['q', 'quick', 'sort', 'dir'])->filter()->isNotEmpty()) open @endif>
  <summary>More filters</summary>
  <form method="get">
    @foreach (['quick', 'sort', 'dir'] as $k)@if (!empty($filters[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}">@endif @endforeach
    <div class="row">
      <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
      <div><label>Status</label><select name="status"><option value="">Any</option>@foreach ($lists['statuses'] as $o)<option value="{{ $o->id }}" @selected(($filters['status'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
      <div><label>Priority</label><select name="priority"><option value="">Any</option>@foreach ($lists['priorities'] as $o)<option value="{{ $o->value }}" @selected(($filters['priority'] ?? '') === $o->value)>{{ $o->label() }}</option>@endforeach</select></div>
      <div><label>Source</label><select name="source"><option value="">Any</option>@foreach ($lists['sources'] as $o)<option value="{{ $o->id }}" @selected(($filters['source'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
    </div>
    <div class="row">
      <div><label>Course</label><select name="course"><option value="">Any</option>@foreach ($lists['courses'] as $o)<option value="{{ $o->id }}" @selected(($filters['course'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
      <div><label>Exam</label><select name="exam"><option value="">Any</option>@foreach ($lists['exams'] as $o)<option value="{{ $o->id }}" @selected(($filters['exam'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
      <div><label>College</label><select name="college"><option value="">Any</option>@foreach ($lists['colleges'] as $o)<option value="{{ $o->id }}" @selected(($filters['college'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
      <div><label>State</label><select name="state"><option value="">Any</option>@foreach ($lists['states'] as $o)<option value="{{ $o->id }}" @selected(($filters['state'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
    </div>
    <div class="row">
      <div><label>City</label><input name="city" value="{{ $filters['city'] ?? '' }}"></div>
      <div><label>Admission year</label><input name="year" type="number" value="{{ $filters['year'] ?? '' }}"></div>
      @unless (auth()->user()->isCounsellor())
      <div><label>Assigned to</label><select name="assigned"><option value="">Anyone</option>
        @can('admin')<option value="unassigned" @selected(($filters['assigned'] ?? '') === 'unassigned')>Unassigned</option>@endcan
        @foreach ($lists['assignable'] as $o)<option value="{{ $o->id }}" @selected(($filters['assigned'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
      @endunless
      <div><label>Follow-up</label><select name="follow_up"><option value="">Any</option>
        @foreach (['today' => 'Today', 'overdue' => 'Overdue', 'upcoming' => 'Upcoming', 'none' => 'No follow-up set'] as $k => $v)<option value="{{ $k }}" @selected(($filters['follow_up'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></div>
      <div><label>Follow-up from</label><input type="date" name="follow_from" value="{{ $filters['follow_from'] ?? '' }}"></div>
      <div><label>Follow-up to</label><input type="date" name="follow_to" value="{{ $filters['follow_to'] ?? '' }}"></div>
    </div>
    <p class="mt-4 mb-0 flex gap-2"><button class="btn green">Apply filters</button> <a class="btn light" href="{{ route('students.index') }}">Clear</a></p>
  </form>
</details>

@php($canBulk = $lists['assignable']->isNotEmpty())
@if ($canBulk)
<form id="bulk" method="post" action="{{ route('students.bulk-assign') }}" class="row card !py-4 items-end !hidden md:!flex">
  @csrf
  <div class="!flex-none w-80"><label class="!mt-0">Assign ticked leads to</label><select name="user_id">
    @can('admin')<option value="">Unassigned</option>@endcan
    @foreach ($lists['assignable'] as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></div>
  <div style="flex:0"><button class="btn">Assign</button></div>
</form>
@endif

@include('partials.student-table', ['selectable' => $canBulk, 'sortable' => true])
@endsection
