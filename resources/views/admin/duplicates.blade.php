@extends('layouts.app')
@section('title', 'Possible duplicates')
@section('content')
<h1>Possible duplicates</h1>
<p class="muted">The CRM already joins enquiries with the same mobile number. These are the doubtful cases for you to check.</p>
<div class="table-wrap"><table>
  <thead><tr><th>Existing student</th><th>Possible duplicate</th><th>Why</th><th>Flagged</th><th>Decision</th></tr></thead>
  @forelse ($reviews as $r)
    <tr>
      <td><a href="{{ route('students.show', $r->student_id) }}">{{ $r->student?->name }}</a><div class="small muted">{{ $r->student?->mobile }}</div></td>
      <td>@if ($r->candidate)<a href="{{ route('students.show', $r->candidate) }}">{{ $r->candidate->name }}</a><div class="small muted">{{ $r->candidate->mobile }}</div>@else <span class="muted">Same record</span>@endif</td>
      <td>{{ $r->reason }}</td>
      <td class="small">{{ $r->created_at->format('j M Y') }}</td>
      <td>
        @if ($r->candidate)
          <form method="post" action="{{ route('admin.duplicates.merge', $r) }}" onsubmit="return confirm('Merge the second record into the first?')">@csrf<button class="btn small">Merge</button></form>
        @endif
        <form method="post" action="{{ route('admin.duplicates.keep', $r) }}" style="margin-top:4px">@csrf<button class="btn small light">{{ $r->candidate ? 'Not a duplicate' : 'Checked, OK' }}</button></form>
      </td>
    </tr>
  @empty
    <tr><td colspan="5" class="muted">Nothing to review.</td></tr>
  @endforelse
</table></div>
<div class="pagination">{{ $reviews->links('partials.pager') }}</div>
@endsection
