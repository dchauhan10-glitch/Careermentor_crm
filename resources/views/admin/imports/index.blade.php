@extends('layouts.app')
@section('title', 'Import Center')
@section('content')
<h1>Import Center</h1>
<div class="card" style="max-width:820px">
  <h2>Upload a list</h2>
  <p class="small muted">Excel (.xlsx) or CSV, up to 10 MB, with column names in the first row. Students already in the CRM (same mobile) get this as a new enquiry; nothing they already have is overwritten.</p>
  <form method="post" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data">
    @csrf
    <label>File</label><input type="file" name="file" accept=".xlsx,.csv" required>
    <div class="row">
      <div><label>Source name (e.g. the college, school or coaching centre)</label>
        <input name="source_name" list="srcs" value="{{ old('source_name') }}" required>
        <datalist id="srcs">@foreach ($sources as $s)<option value="{{ $s->name }}">@endforeach</datalist></div>
      <div><label>Type of source</label><select name="source_type">
        <option value="physical_upload">College / school / coaching list</option>
        <option value="sheet_import">Old Google Sheet sign-ups</option>
        <option value="other">Other authorized source</option></select></div>
      <div><label>Date the data was received</label><input type="date" name="collected_on" value="{{ old('collected_on', today()->toDateString()) }}" required></div>
    </div>
    <p><button class="btn">Upload and check columns</button></p>
  </form>
</div>

<h2>Import history</h2>
<div class="table-wrap"><table>
  <thead><tr><th>Uploaded</th><th>File</th><th>Source</th><th>Rows</th><th>New</th><th>Added to existing</th><th>To review</th><th>Rejected</th><th>Status</th></tr></thead>
  @forelse ($batches as $b)
    <tr>
      <td class="small">{{ $b->created_at->format('j M Y, g:i A') }}<div class="muted">{{ $b->uploader->name }}</div></td>
      <td><a href="{{ $b->status === 'uploaded' ? route('admin.imports.map', $b) : route('admin.imports.show', $b) }}">{{ $b->original_filename }}</a></td>
      <td>{{ $b->source->name }}</td><td>{{ number_format($b->total_rows) }}</td>
      <td>{{ number_format($b->created_count) }}</td><td>{{ number_format($b->merged_count) }}</td>
      <td>{{ number_format($b->review_count) }}</td><td @class(['due' => $b->error_count])>{{ number_format($b->error_count) }}</td>
      <td>{{ ucfirst($b->status) }}@if (in_array($b->status, ['queued', 'processing'])) ({{ $b->percent() }}%)@endif</td>
    </tr>
  @empty
    <tr><td colspan="9" class="muted">No imports yet.</td></tr>
  @endforelse
</table></div>
<div class="pagination">{{ $batches->links('partials.pager') }}</div>
@endsection
