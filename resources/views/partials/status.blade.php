@php($statusName = is_string($s ?? null) ? $s : ($s?->name ?? null))
@if ($statusName)<span class="badge st-{{ \App\Support\StatusTone::tone($statusName) }}">{{ $statusName }}</span>@else<span class="muted">None</span>@endif
