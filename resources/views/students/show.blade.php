@extends('layouts.app')
@section('title', $student->name)
@section('content')
@php($me = auth()->user())
<div class="card">
  <div class="profile">
    <span class="avatar" aria-hidden="true">{{ collect(explode(' ', $student->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</span>
    <div class="meta">
      <h1>{{ $student->name }}</h1>
      <div class="small muted">{{ $student->student_code }}@if ($student->email) · {{ $student->email }}@endif
        @if ($student->parent_mobile) · Parent: <a href="tel:+91{{ $student->parent_mobile }}">{{ $student->parent_mobile }}</a>@endif</div>
      <div class="quick">
        <a class="btn" href="tel:+91{{ $student->mobile }}">@include('partials.icon', ['name' => 'phone']){{ $student->mobile }}</a>
        <a class="btn light" href="https://wa.me/91{{ $student->mobile }}" target="_blank" rel="noopener">@include('partials.icon', ['name' => 'chat'])WhatsApp</a>
        @can('work', $student)<a class="btn accent" href="#log-call">@include('partials.icon', ['name' => 'plus'])Log a call</a>@endcan
      </div>
    </div>
  </div>
  <div class="facts-strip">
    <div><span>Status</span><b>{{ $student->status?->name ?? 'None' }}</b></div>
    <div><span>Priority</span>@include('partials.priority', ['p' => $student->priority])</div>
    <div><span>Next follow-up</span><b>@include('partials.follow', ['at' => $student->next_follow_up_at])</b></div>
    <div><span>Assigned to</span><b>{{ $student->assignedUser?->name ?? 'Unassigned' }}</b>@if ($student->team)<span>{{ $student->team->name }}</span>@endif</div>
  </div>
</div>

<div class="grid split">
  <div>
    @can('work', $student)
    <div class="card" id="log-call">
      <h2>Log a call</h2>
      <form method="post" action="{{ route('students.calls.store', $student) }}">
        @csrf
        <label for="outcome">What did the student say? *</label>
        <input id="outcome" name="outcome" value="{{ old('outcome') }}" required maxlength="255" placeholder="e.g. Interested, wants fee details">
        <label for="advice">Advice or suggestion given</label>
        <textarea id="advice" name="advice">{{ old('advice') }}</textarea>
        <label for="remarks">Remarks</label>
        <textarea id="remarks" name="remarks">{{ old('remarks') }}</textarea>
        <div class="row">
          <div><label>New status</label><select name="lead_status_id"><option value="">No change</option>
            @foreach ($lists['statuses'] as $o)<option value="{{ $o->id }}" @selected(old('lead_status_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
          <div><label>Priority</label><select name="priority">
            @foreach ($lists['priorities'] as $o)<option value="{{ $o->value }}" @selected(old('priority', $student->priority?->value) === $o->value)>{{ $o->label() }}</option>@endforeach</select></div>
          <div><label>Next follow-up</label><input type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at') }}"></div>
        </div>
        @if ($student->callEntries->isNotEmpty())
          <label>This corrects an earlier entry (optional)</label>
          <select name="corrects_call_entry_id"><option value="">No, this is a new call</option>
            @foreach ($student->callEntries as $c)<option value="{{ $c->id }}">{{ $c->called_at->format('j M Y, g:i A') }} · {{ \Illuminate\Support\Str::limit($c->outcome, 50) }}</option>@endforeach</select>
        @endif
        <p><button class="btn">Save call</button> <span class="small muted">Saved calls cannot be edited or deleted.</span></p>
      </form>
    </div>
    @endcan

    <div class="card">
      <h2>Call history ({{ $student->callEntries->count() }})</h2>
      @if ($student->callEntries->isEmpty())<p class="muted">No calls yet.</p>@endif
      <ul class="timeline">
        @foreach ($student->callEntries as $c)
          <li @class(['corr' => $c->corrects_call_entry_id])>
            <div class="meta">{{ $c->called_at->format('D j M Y, g:i A') }} · {{ $c->user->name }}
              @if ($c->status) · status set to {{ $c->status->name }}@endif
              @if ($c->corrects_call_entry_id) · <b>correction</b> of the entry from {{ $student->callEntries->firstWhere('id', $c->corrects_call_entry_id)?->called_at->format('j M, g:i A') }}@endif
            </div>
            <div><b>{{ $c->outcome }}</b></div>
            @if ($c->advice)<div><span class="muted">Advice:</span> {{ $c->advice }}</div>@endif
            @if ($c->remarks)<div><span class="muted">Remarks:</span> {{ $c->remarks }}</div>@endif
            @if ($c->next_follow_up_at)<div class="small muted">Next follow-up set: {{ $c->next_follow_up_at->format('j M Y, g:i A') }}</div>@endif
          </li>
        @endforeach
      </ul>
    </div>
  </div>

  <div>
    @can('assign', $student)
    <div class="card">
      <h2>Assign</h2>
      <form method="post" action="{{ route('students.assign', $student) }}" class="row" style="align-items:end">
        @csrf
        <div><select name="user_id" aria-label="Assign to">
          @can('admin')<option value="">Unassigned</option>@endcan
          @foreach ($lists['assignable'] as $o)<option value="{{ $o->id }}" @selected($student->assigned_user_id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
        <div style="flex:0"><button class="btn">Assign</button></div>
      </form>
    </div>
    @endcan

    <div class="card">
      <h2>Details</h2>
      <dl class="facts">
        <dt>Course</dt><dd>{{ $student->course?->name }}</dd>
        <dt>College</dt><dd>{{ $student->college?->name }}</dd>
        <dt>Exam / rank</dt><dd>{{ $student->exam?->name }} @if ($student->exam_rank) · rank {{ number_format($student->exam_rank) }}@endif</dd>
        <dt>City / state</dt><dd>{{ $student->city_text }}@if ($student->state) · {{ $student->state->name }}@endif</dd>
        <dt>Admission year</dt><dd>{{ $student->admission_year }}</dd>
        <dt>First source</dt><dd>{{ $student->firstSource?->name }}</dd>
        <dt>Remarks</dt><dd>{{ $student->remarks }}</dd>
        <dt>Added</dt><dd>{{ $student->created_at->format('j M Y') }}</dd>
      </dl>
      @can('editDetails', $student)
      <details style="margin-top:10px"><summary>Edit details</summary>
        <form method="post" action="{{ route('students.update', $student) }}">
          @csrf @method('put')
          <label>Name</label><input name="name" value="{{ old('name', $student->name) }}" required>
          <div class="row">
            <div><label>Parent mobile</label><input name="parent_mobile" value="{{ old('parent_mobile', $student->parent_mobile) }}"></div>
            <div><label>Email</label><input name="email" type="email" value="{{ old('email', $student->email) }}"></div>
          </div>
          <div class="row">
            <div><label>Course</label><select name="course_id"><option value=""></option>@foreach ($lists['courses'] as $o)<option value="{{ $o->id }}" @selected($student->course_id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
            <div><label>College</label><select name="college_id"><option value=""></option>@foreach ($lists['colleges'] as $o)<option value="{{ $o->id }}" @selected($student->college_id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
          </div>
          <div class="row">
            <div><label>Exam</label><select name="exam_id"><option value=""></option>@foreach ($lists['exams'] as $o)<option value="{{ $o->id }}" @selected($student->exam_id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
            <div><label>Rank</label><input name="exam_rank" type="number" value="{{ old('exam_rank', $student->exam_rank) }}"></div>
          </div>
          <div class="row">
            <div><label>City</label><input name="city_text" value="{{ old('city_text', $student->city_text) }}"></div>
            <div><label>State</label><select name="state_id"><option value=""></option>@foreach ($lists['states'] as $o)<option value="{{ $o->id }}" @selected($student->state_id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
            <div><label>Admission year</label><input name="admission_year" type="number" value="{{ old('admission_year', $student->admission_year) }}"></div>
          </div>
          <label>Remarks</label><textarea name="remarks">{{ old('remarks', $student->remarks) }}</textarea>
          <p><button class="btn">Save details</button></p>
        </form>
      </details>
      @endcan
    </div>

    <div class="card">
      <h2>Enquiries ({{ $student->enquiries->count() }})</h2>
      @foreach ($student->enquiries as $e)
        <div class="small" style="padding:4px 0;border-bottom:1px solid var(--line)">
          <b>{{ $e->source->name }}</b> · {{ $e->collected_on->format('j M Y') }} · {{ ucfirst($e->channel) }}
          <div class="muted">{{ collect([$e->course_text, $e->exam_text, $e->city_state_text, $e->source_page])->filter()->join(' · ') }}</div>
        </div>
      @endforeach
    </div>

    <div class="card">
      <h2>Status and assignment history</h2>
      @foreach ($student->statusHistory as $h)
        <div class="small">{{ $h->created_at->format('j M, g:i A') }} · {{ $h->changedBy?->name }}: {{ $h->toStatus?->name }}@if ($h->to_priority) ({{ ucfirst($h->to_priority) }})@endif</div>
      @endforeach
      @foreach ($student->assignmentHistory as $h)
        <div class="small">{{ $h->created_at->format('j M, g:i A') }} · {{ $h->assignedBy?->name }} assigned to {{ $h->toUser?->name ?? 'Unassigned' }}</div>
      @endforeach
    </div>

    @can('delete', $student)
      <form method="post" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Move this student to deleted records?')">
        @csrf @method('delete')<button class="btn danger small">Delete student (can be restored)</button>
      </form>
    @endcan
  </div>
</div>
@endsection
