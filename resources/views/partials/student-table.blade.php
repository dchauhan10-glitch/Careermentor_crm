@php
  $sortable = $sortable ?? false;
  $sortLink = function (string $key, string $label) use ($sortable) {
      if (! $sortable) return e($label);
      $on = request('sort') === $key;
      $dir = $on && request('dir', 'asc') === 'asc' ? 'desc' : 'asc';
      $icon = view('partials.icon', ['name' => $on ? (request('dir', 'asc') === 'asc' ? 'arrow-up' : 'arrow-down') : 'sort'])->render();
      return '<a class="sort'.($on ? ' on' : '').'" href="'.e(request()->fullUrlWithQuery(['sort' => $key, 'dir' => $dir, 'page' => null])).'">'.e($label).$icon.'</a>';
  };
@endphp
<div class="table-wrap desk-only">
<table>
  <thead><tr>
    @if (!empty($selectable))<th class="w-10"><input type="checkbox" aria-label="Select all" onclick="document.querySelectorAll('.pick').forEach(c=>c.checked=this.checked)"></th>@endif
    <th>{!! $sortLink('name', 'Student') !!}</th><th>Mobile</th><th>Course</th><th>{!! $sortLink('status', 'Status') !!}</th><th>Priority</th><th>{!! $sortLink('follow', 'Next follow-up') !!}</th><th>{!! $sortLink('assigned', 'Assigned to') !!}</th>
  </tr></thead>
  <tbody>
  @forelse ($students as $s)
    <tr>
      @if (!empty($selectable))<td><input class="pick" type="checkbox" name="student_ids[]" value="{{ $s->id }}" form="bulk" aria-label="Select {{ $s->name }}"></td>@endif
      <td><div class="stu-cell"><span class="stu-av">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</span>
        <div class="min-w-0"><a class="stu-name" href="{{ route('students.show', $s) }}">{{ $s->name }}</a><div class="small muted">{{ $s->student_code }}</div></div></div></td>
      <td class="whitespace-nowrap"><a class="inline-flex items-center gap-1.5 font-semibold" href="tel:+91{{ $s->mobile }}">@include('partials.icon', ['name' => 'phone', 'class' => 'w-4 h-4'])<span>{{ $s->mobile }}</span></a></td>
      <td class="text-slate-600">{{ $s->course?->name ?? '' }}</td>
      <td>@include('partials.status', ['s' => $s->status])</td>
      <td>@include('partials.priority', ['p' => $s->priority])</td>
      <td>@include('partials.follow', ['at' => $s->next_follow_up_at])</td>
      <td class="text-slate-600 whitespace-nowrap">{{ $s->assignedUser?->name ?? 'Unassigned' }}</td>
    </tr>
  @empty
    <tr><td colspan="8"><div class="empty">No leads found.</div></td></tr>
  @endforelse
  </tbody>
</table>
</div>

{{-- Phones: one card per lead, with a big call button --}}
<ul class="lead-cards">
  @forelse ($students as $s)
    <li class="lead-card">
      <span class="stu-av !w-11 !h-11">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</span>
      <div class="main">
        <a href="{{ route('students.show', $s) }}">{{ $s->name }}</a>
        <div class="sub">@include('partials.status', ['s' => $s->status])
          @if ($s->next_follow_up_at)@include('partials.follow', ['at' => $s->next_follow_up_at])@endif</div>
      </div>
      <a class="dial" href="tel:+91{{ $s->mobile }}" aria-label="Call {{ $s->name }}">@include('partials.icon', ['name' => 'phone'])</a>
    </li>
  @empty
    <li class="empty">No leads found.</li>
  @endforelse
</ul>
<div class="pagination">{{ $students->links('partials.pager', ['noun' => 'leads']) }}</div>
