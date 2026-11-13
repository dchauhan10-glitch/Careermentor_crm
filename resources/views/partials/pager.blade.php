@if ($paginator->hasPages())
<nav class="small" aria-label="Pages">
  @if (!$paginator->onFirstPage())<a class="btn small light" href="{{ $paginator->previousPageUrl() }}">@include('partials.icon', ['name' => 'chevron', 'class' => 'rotate-180'])Previous</a>@endif
  <span class="muted px-2">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} · {{ number_format($paginator->total()) }} {{ $noun ?? 'students' }}</span>
  @if ($paginator->hasMorePages())<a class="btn small light" href="{{ $paginator->nextPageUrl() }}">Next@include('partials.icon', ['name' => 'chevron'])</a>@endif
</nav>
@endif
