@extends('layouts.app')
@section('title', 'Match columns')
@section('content')
<h1>Match columns: {{ $batch->original_filename }}</h1>
<p class="muted">{{ number_format($batch->total_rows) }} rows · source <b>{{ $batch->source->name }}</b> · received {{ $batch->collected_on->format('j M Y') }}</p>

<form method="post" action="{{ route('admin.imports.start', $batch) }}" id="mapform">
  @csrf
  <div class="card">
    <h2>Which column holds what?</h2>
    <div class="grid g3">
      @foreach ($fields as $field => $label)
        <div><label>{{ $label }}</label>
          <select name="map[{{ $field }}]" onchange="document.getElementById('refresh').click()">
            <option value="">(not in this file)</option>
            @foreach ($batch->headers as $i => $h)<option value="{{ $i }}" @selected($map[$field] === $i)>{{ $h ?: 'Column '.($i + 1) }}</option>@endforeach
          </select></div>
      @endforeach
    </div>
    <p><button class="btn light" id="refresh" formaction="{{ route('admin.imports.map', $batch) }}" formmethod="get">Refresh preview</button></p>
  </div>

  <div class="card">
    <h2>Preview of the first rows</h2>
    <div class="table-wrap"><table>
      <thead><tr>@foreach ($fields as $f => $l)<th>{{ rtrim($l, ' *') }}</th>@endforeach<th>Check</th></tr></thead>
      @foreach ($preview as $p)
        <tr>@foreach ($fields as $f => $l)<td class="small">{{ $p['data'][$f] ?? '' }}</td>@endforeach
          <td class="small">@if ($p['problem'])<span class="due">{{ $p['problem'] }}</span>@else OK @endif</td></tr>
      @endforeach
    </table></div>
    <p class="small muted">Rows with a problem are skipped and listed after the import. All other rows are imported.</p>
  </div>

  <p><button class="btn">Start import</button>
    <button class="btn light" formaction="{{ route('admin.imports.cancel', $batch) }}" formmethod="post" onclick="return confirm('Cancel this upload?')">Cancel</button></p>
</form>
@endsection
