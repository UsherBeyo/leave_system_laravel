@if ($paginator->hasPages())
    <nav class="pagination-bar" role="navigation" aria-label="Pagination Navigation">
        <div class="pagination-summary">
            Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} results
        </div>

        <div class="pagination-links">
            @if ($paginator->onFirstPage())
                <span class="pagination-link is-disabled" aria-disabled="true">&lsaquo; Prev</span>
            @else
                <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo; Prev</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-ellipsis">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-link is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pagination-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next &rsaquo;</a>
            @else
                <span class="pagination-link is-disabled" aria-disabled="true">Next &rsaquo;</span>
            @endif
        </div>
    </nav>
@endif
