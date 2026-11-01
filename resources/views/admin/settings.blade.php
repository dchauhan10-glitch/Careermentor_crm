@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<h1>Settings</h1>
<p class="muted">Switching an item off hides it from drop-downs. Records that already use it are not changed.</p>
@php
  $blocks = [
    'statuses' => ['Lead statuses', $statuses],
    'sources' => ['Lead sources', $sources],
    'courses' => ['Courses', $courses],
    'exams' => ['Entrance exams', $exams],
    'colleges' => ['Colleges', $colleges],
  ];
@endphp
<div class="grid g2">
@foreach ($blocks as $key => [$title, $items])
  <div class="card">
    <h2>{{ $title }}</h2>
    <div style="max-height:260px;overflow:auto">
    <table>
      @foreach ($items as $i)
        <tr><td>{{ $i->name }}
            @if ($key === 'statuses' && $i->is_closed)<span class="pill">closes the lead</span>@endif
            @if ($key === 'sources')<span class="small muted">{{ \App\Models\Source::TYPES[$i->type] ?? $i->type }}</span>@endif
            @unless ($i->is_active)<span class="pill">Off</span>@endunless</td>
          <td style="text-align:right"><form method="post" action="{{ route('admin.settings.toggle', [$key, $i->id]) }}">@csrf<button class="btn small light">{{ $i->is_active ? 'Switch off' : 'Switch on' }}</button></form></td></tr>
      @endforeach
    </table>
    </div>
    <form method="post" action="{{ route('admin.settings.add', $key) }}" class="row" style="margin-top:10px;align-items:end">
      @csrf
      <div><input name="name" placeholder="Add new" required></div>
      @if ($key === 'statuses')<div style="flex:0;min-width:160px"><label style="margin:0"><input type="checkbox" name="is_closed" value="1" style="width:auto"> Closes the lead</label></div>@endif
      @if ($key === 'sources')<div><select name="type">@foreach (\App\Models\Source::TYPES as $t => $l)<option value="{{ $t }}">{{ $l }}</option>@endforeach</select></div>@endif
      <div style="flex:0"><button class="btn small">Add</button></div>
    </form>
  </div>
@endforeach
</div>

<div class="card">
  <h2>Recent activity log</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>When</th><th>Who</th><th>What</th><th>Record</th></tr></thead>
    @foreach ($recentAudit as $a)
      <tr class="small"><td>{{ $a->created_at->format('j M, g:i A') }}</td><td>{{ $a->user?->name ?? 'System' }}</td><td>{{ str_replace(['.', '_'], ' ', $a->action) }}</td><td>{{ $a->subject_type }} {{ $a->subject_id }}</td></tr>
    @endforeach
  </table></div>
</div>
@endsection
