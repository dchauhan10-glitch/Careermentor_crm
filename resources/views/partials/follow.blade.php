@if ($at)
  <span @class(['due' => $at->lt(today())])>{{ $at->isToday() ? 'Today '.$at->format('g:i A') : $at->format('j M Y, g:i A') }}</span>
@else
  <span class="muted">None</span>
@endif
