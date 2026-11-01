@extends('layouts.app')
@section('title', 'Import')
@section('content')
@if (in_array($batch->status, ['queued', 'processing']))<meta http-equiv="refresh" content="5">@endif
<h1>Import: {{ $batch->original_filename }}</h1>
<p class="muted">Source <b>{{ $batch->source->name }}</b> · received {{ $batch->collected_on->format('j M Y') }} · uploaded by {{ $batch->uploader->name }} on {{ $batch->created_at->format('j M Y, g:i A') }}</p>
<div class="tiles">
  <div class="tile"><b>{{ ucfirst($batch->status) }}</b><span>{{ $batch->percent() }}% done</span></div>
  <div class="tile"><b>{{ number_format($batch->processed_rows) }} / {{ number_format($batch->total_rows) }}</b><span>Rows checked</span></div>
  <div class="tile"><b>{{ number_format($batch->created_count) }}</b><span>New students</span></div>
  <div class="tile"><b>{{ number_format($batch->merged_count) }}</b><span>Added to existing students</span></div>
  <a class="tile" href="{{ route('admin.duplicates') }}"><b>{{ number_format($batch->review_count) }}</b><span>Sent to duplicate review</span></a>
  <div class="tile {{ $batch->error_count ? 'alert' : '' }}"><b>{{ number_format($batch->error_count) }}</b><span>Rejected rows</span></div>
</div>
@if (in_array($batch->status, ['queued', 'processing']))<p class="muted">This page refreshes every few seconds. You can leave it; the import carries on.</p>@endif
@if ($batch->status === 'completed')<p><a class="btn light" href="{{ route('students.index', ['source' => $batch->source_id]) }}">See students from {{ $batch->source->name }}</a></p>@endif
@if ($errors_list->isNotEmpty())
  <div class="card"><h2>Rejected rows</h2>
    <p class="small muted">Row numbers match the original file (row 1 is the header). Fix these in the file and upload just those rows again.</p>
    <table>@foreach ($errors_list as $e)<tr><td style="width:90px">Row {{ $e->row_number }}</td><td>{{ $e->message }}</td></tr>@endforeach</table>
  </div>
@endif
@endsection
