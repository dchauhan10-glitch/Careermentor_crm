<div class="table-wrap">
<table>
  <thead><tr>
    @if (!empty($selectable))<th><input type="checkbox" aria-label="Select all" onclick="document.querySelectorAll('.pick').forEach(c=>c.checked=this.checked)"></th>@endif
    <th>Student</th><th>Mobile</th><th>Course</th><th>Status</th><th>Priority</th><th>Next follow-up</th><th>Assigned to</th>
  </tr></thead>
  <tbody>
  @forelse ($students as $s)
    <tr>
      @if (!empty($selectable))<td><input class="pick" type="checkbox" name="student_ids[]" value="{{ $s->id }}" form="bulk"></td>@endif
      <td><a href="{{ route('students.show', $s) }}"><b>{{ $s->name }}</b></a><div class="small muted">{{ $s->student_code }}</div></td>
      <td><a href="tel:+91{{ $s->mobile }}">{{ $s->mobile }}</a></td>
      <td>{{ $s->course?->name ?? '' }}</td>
      <td>{{ $s->status?->name }}</td>
      <td>@include('partials.priority', ['p' => $s->priority])</td>
      <td>@include('partials.follow', ['at' => $s->next_follow_up_at])</td>
      <td>{{ $s->assignedUser?->name ?? 'Unassigned' }}</td>
    </tr>
  @empty
    <tr><td colspan="8" class="muted">No students found.</td></tr>
  @endforelse
  </tbody>
</table>
</div>
<div class="pagination">{{ $students->links('partials.pager') }}</div>
