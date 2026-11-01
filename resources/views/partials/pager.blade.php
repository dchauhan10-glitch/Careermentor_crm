@if ($paginator->hasPages())
<nav class="small">
  @if (!$paginator->onFirstPage())<a class="btn small light" href="{{ $paginator->previousPageUrl() }}">Previous</a>@endif
  <span class="muted" style="padding:4px 8px">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} ({{ number_format($paginator->total()) }} students)</span>
  @if ($paginator->hasMorePages())<a class="btn small light" href="{{ $paginator->nextPageUrl() }}">Next</a>@endif
</nav>
@endif
