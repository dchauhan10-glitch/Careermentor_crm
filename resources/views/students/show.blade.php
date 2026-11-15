@extends('layouts.app')
@section('title', $student->name)
@section('content')
@php($me = auth()->user())
<a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('students.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold mb-3">@include('partials.icon', ['name' => 'chevron', 'class' => 'w-4 h-4 rotate-180'])Back</a>
<div class="card">
  <div class="profile">
    <span class="avatar" aria-hidden="true">{{ collect(explode(' ', $student->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</span>
    <div class="meta">
      <div class="flex flex-wrap items-center gap-2"><h1>{{ $student->name }}</h1>@include('partials.status', ['s' => $student->status])</div>
      <div class="small muted">{{ $student->student_code }} · {{ $student->mobile }}@if ($student->email) · {{ $student->email }}@endif
        @if ($student->parent_mobile) · Parent: <a href="tel:+91{{ $student->parent_mobile }}">{{ $student->parent_mobile }}</a>@endif</div>
    </div>
    <div class="callbar w-full lg:w-auto">
      <a class="bigcall" href="tel:+91{{ $student->mobile }}">@include('partials.icon', ['name' => 'phone'])Call {{ $student->mobile }}</a>
      <a class="bigwa" href="https://wa.me/91{{ $student->mobile }}" target="_blank" rel="noopener" aria-label="WhatsApp">@include('partials.icon', ['name' => 'whatsapp'])<span class="hidden sm:inline">WhatsApp</span></a>
    </div>
  </div>
  <div class="facts-strip">
    <div><span>Status</span>@include('partials.status', ['s' => $student->status])</div>
    <div><span>Priority</span>@include('partials.priority', ['p' => $student->priority])</div>
    <div><span>Next follow-up</span>@include('partials.follow', ['at' => $student->next_follow_up_at])</div>
    <div><span>Assigned to</span><b>{{ $student->assignedUser?->name ?? 'Unassigned' }}</b>@if ($student->team)<span class="block text-xs text-slate-500">{{ $student->team->name }}</span>@endif</div>
  </div>
</div>

<div class="grid split">
  <div>
    @can('work', $student)
    <div class="card" id="log-call">
      <div class="card-head"><h2>After the call</h2><span class="locked ml-auto">@include('partials.icon', ['name' => 'lock'])Saved calls can't be edited</span></div>
      <form method="post" action="{{ route('students.calls.store', $student) }}" data-callform>
        @csrf
        <label class="!mt-0">Result of the call</label>
        <div class="choice" role="radiogroup" aria-label="New status">
          <label class="c-none"><input type="radio" name="lead_status_id" value="" @checked(old('lead_status_id', '') === '')><span>No change</span></label>
          @foreach ($lists['statuses'] as $o)
            <label class="c-{{ \App\Support\StatusTone::tone($o->name) }}"><input type="radio" name="lead_status_id" value="{{ $o->id }}" data-name="{{ $o->name }}" @checked(old('lead_status_id') == $o->id)><span>{{ $o->name }}</span></label>
          @endforeach
        </div>
        <label for="outcome">What did the student say? *</label>
        <input id="outcome" name="outcome" value="{{ old('outcome') }}" required maxlength="255" placeholder="e.g. Interested, wants fee details">
        <label for="advice">Suggestion given</label>
        <textarea id="advice" name="advice" rows="2" class="!min-h-0">{{ old('advice') }}</textarea>
        <label for="remarks">Remarks</label>
        <textarea id="remarks" name="remarks" rows="2" class="!min-h-0">{{ old('remarks') }}</textarea>
        <div class="row">
          <div class="!min-w-[230px]"><label for="next_follow_up_at">Next follow-up</label><input id="next_follow_up_at" type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at') }}">
            <div class="quickdates"><button type="button" data-days="1">Tomorrow</button><button type="button" data-days="3">In 3 days</button><button type="button" data-days="7">Next week</button></div></div>
          <div><label for="priority">Priority</label><select id="priority" name="priority">
            @foreach ($lists['priorities'] as $o)<option value="{{ $o->value }}" @selected(old('priority', $student->priority?->value) === $o->value)>{{ $o->label() }}</option>@endforeach</select></div>
        </div>
        @if ($student->callEntries->isNotEmpty())
          <details class="mt-4"><summary class="text-sm">Correcting an earlier entry?</summary>
            <select name="corrects_call_entry_id" class="mt-2" aria-label="Entry being corrected"><option value="">No, this is a new call</option>
              @foreach ($student->callEntries as $c)<option value="{{ $c->id }}">{{ $c->called_at->format('j M Y, g:i A') }} · {{ \Illuminate\Support\Str::limit($c->outcome, 50) }}</option>@endforeach</select>
          </details>
        @endif
        <button class="btn w-full !h-12 !text-base mt-5">@include('partials.icon', ['name' => 'check'])Save call</button>
      </form>
    </div>
    @endcan

    <div class="card">
      <div class="card-head"><h2>Call history ({{ $student->callEntries->count() }})</h2><span class="locked ml-auto">@include('partials.icon', ['name' => 'lock'])Read only</span></div>
      @if ($student->callEntries->isEmpty())<p class="muted">No calls yet.</p>@endif
      <ul class="timeline">
        @foreach ($student->callEntries as $c)
          <li @class(['corr' => $c->corrects_call_entry_id])>
            <div class="meta">{{ $c->called_at->format('D j M Y, g:i A') }} · {{ $c->user->name }}
              @if ($c->status) · status set to {{ $c->status->name }}@endif
              @if ($c->corrects_call_entry_id) · <b>correction</b> of the entry from {{ $student->callEntries->firstWhere('id', $c->corrects_call_entry_id)?->called_at->format('j M, g:i A') }}@endif
            </div>
            <div class="entry">
            <div><b>{{ $c->outcome }}</b></div>
            @if ($c->advice)<div class="text-sm"><span class="muted">Suggestion:</span> {{ $c->advice }}</div>@endif
            @if ($c->remarks)<div class="text-sm"><span class="muted">Remarks:</span> {{ $c->remarks }}</div>@endif
            @if ($c->next_follow_up_at)<div class="small muted">Next follow-up set: {{ $c->next_follow_up_at->format('j M Y, g:i A') }}</div>@endif
            </div>
          </li>
        @endforeach
      </ul>
    </div>
  </div>

  <div>
    @can('assign', $student)
    <div class="card">
      <h2>Assign</h2>
      <form method="post" action="{{ route('students.assign', $student) }}" class="row items-end">
        @csrf
        <div><select name="user_id" aria-label="Assign to">
          @can('admin')<option value="">Unassigned</option>@endcan
          @foreach ($lists['assignable'] as $o)<option value="{{ $o->id }}" @selected($student->assigned_user_id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
        <div class="!flex-none"><button class="btn green">Assign</button></div>
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
      <details class="mt-4"><summary>Edit details</summary>
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
          <p class="mt-4 mb-0"><button class="btn green">Save details</button></p>
        </form>
      </details>
      @endcan
    </div>

    <div class="card">
      <h2>Enquiries ({{ $student->enquiries->count() }})</h2>
      @foreach ($student->enquiries as $e)
        <div class="small py-2.5 border-b border-line last:border-0">
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
<script>
(function () {
  var f = document.querySelector('[data-callform]'); if (!f) return;
  var out = f.querySelector('#outcome'), when = f.querySelector('#next_follow_up_at');
  f.querySelectorAll('input[name=lead_status_id]').forEach(function (r) {
    r.addEventListener('change', function () { if (r.dataset.name && !out.value.trim()) out.value = r.dataset.name; });
  });
  f.querySelectorAll('[data-days]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = new Date(); d.setDate(d.getDate() + +b.dataset.days); d.setHours(11, 0, 0, 0);
      var p = function (n) { return String(n).padStart(2, '0'); };
      when.value = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T11:00';
    });
  });
})();
</script>
@endsection
