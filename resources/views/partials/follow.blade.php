@if ($at)
  @if ($at->lt(today()))
    <span class="badge st-amber" title="Overdue">Overdue · {{ $at->format('j M') }}</span>
  @elseif ($at->isToday())
    <span class="badge st-amber" title="Due today">Today {{ $at->format('g:i A') }}</span>
  @else
    <span class="text-slate-600 whitespace-nowrap">{{ $at->format('j M Y, g:i A') }}</span>
  @endif
@else
  <span class="muted">None</span>
@endif
