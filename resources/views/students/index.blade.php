@extends('layouts.app')
@section('title', 'Students')
@section('content')
<div class="row" style="align-items:center;margin-bottom:10px">
  <h1 style="margin:0">Students</h1>
  @can('create', App\Models\Student::class)<div style="flex:0"><a class="btn" href="{{ route('students.create') }}">Add student</a></div>@endcan
</div>

<details class="card" @if (collect($filters)->except('q')->filter()->isNotEmpty()) open @endif>
  <summary>Filters</summary>
  <form method="get">
    <div class="row">
      <div><label>Search</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Mobile, name, ID or email"></div>
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
    <p><button class="btn">Apply filters</button> <a class="btn light" href="{{ route('students.index') }}">Clear</a></p>
  </form>
</details>

@php($canBulk = $lists['assignable']->isNotEmpty())
@if ($canBulk)
<form id="bulk" method="post" action="{{ route('students.bulk-assign') }}" class="row card" style="align-items:end">
  @csrf
  <div><label>Assign ticked students to</label><select name="user_id">
    @can('admin')<option value="">Unassigned</option>@endcan
    @foreach ($lists['assignable'] as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></div>
  <div style="flex:0"><button class="btn">Assign</button></div>
</form>
@endif

@include('partials.student-table', ['selectable' => $canBulk])
@endsection
