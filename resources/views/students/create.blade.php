@extends('layouts.app')
@section('title', 'Add student')
@section('content')
<div class="card" style="max-width:720px">
  <h1>Add student</h1>
  <p class="muted small">If the mobile number already exists, this is added to that student as a new enquiry.</p>
  <form method="post" action="{{ route('students.store') }}">
    @csrf
    <div class="row">
      <div><label>Student name *</label><input name="name" value="{{ old('name') }}" required></div>
      <div><label>Mobile *</label><input name="mobile" value="{{ old('mobile') }}" required inputmode="tel"></div>
    </div>
    <div class="row">
      <div><label>Parent mobile</label><input name="parent_mobile" value="{{ old('parent_mobile') }}" inputmode="tel"></div>
      <div><label>Email</label><input name="email" type="email" value="{{ old('email') }}"></div>
    </div>
    <div class="row">
      <div><label>Course interested in</label><input name="course" value="{{ old('course') }}" list="courses"></div>
      <div><label>Exam</label><input name="exam" value="{{ old('exam') }}" list="exams"></div>
    </div>
    <div class="row">
      <div><label>City or state</label><input name="city_state" value="{{ old('city_state') }}"></div>
      <div><label>Admission year</label><input name="admission_year" type="number" value="{{ old('admission_year', now()->year) }}"></div>
      <div><label>Source *</label><select name="source_id" required>@foreach ($lists['sources'] as $o)<option value="{{ $o->id }}" @selected(old('source_id') == $o->id || (!old('source_id') && $o->name === 'Manual entry'))>{{ $o->name }}</option>@endforeach</select></div>
    </div>
    <datalist id="courses">@foreach ($lists['courses'] as $o)<option value="{{ $o->name }}">@endforeach</datalist>
    <datalist id="exams">@foreach ($lists['exams'] as $o)<option value="{{ $o->name }}">@endforeach</datalist>
    <p><button class="btn">Save student</button></p>
  </form>
</div>
@endsection
