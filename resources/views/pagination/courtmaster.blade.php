@if ($paginator->hasPages())
    <nav class="d-flex align-items-center justify-content-between gap-3" aria-label="Pagination">

        {{-- Desktop: "Showing X–Y of Z" summary (left) --}}
        <p class="small text-muted mb-0 d-none d-md-block">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>

        {{-- Desktop: full numbered pagination (right) --}}
        <ul class="pagination mb-0 d-none d-md-flex">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">&lsaquo;</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo;</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">&rsaquo;</a></li>
            @else
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">&rsaquo;</span></li>
            @endif
        </ul>

        {{-- Mobile: compact Previous · Page X of Y · Next --}}
        <div class="d-flex d-md-none w-100 align-items-center justify-content-between gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-outline-secondary btn-sm disabled">
                    <i class="bi bi-chevron-left"></i>
                </span>
            @else
                <a class="btn btn-outline-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                    <i class="bi bi-chevron-left"></i>
                </a>
            @endif

            <span class="small text-muted">
                Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">
                    <i class="bi bi-chevron-right"></i>
                </a>
            @else
                <span class="btn btn-outline-secondary btn-sm disabled">
                    <i class="bi bi-chevron-right"></i>
                </span>
            @endif
        </div>
    </nav>
@endif
