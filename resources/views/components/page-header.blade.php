@props([
    'title',
    'subtitle' => null,
    'back'     => null,
    'backLabel'=> 'Back',
])

<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pb-3 mb-4 border-bottom">
    <div class="d-flex align-items-start gap-2 min-w-0">
        @if($back)
        <a href="{{ $back }}" class="btn btn-link btn-sm p-1 mt-1 text-muted flex-shrink-0">
            <i class="bi bi-arrow-left"></i>
        </a>
        @endif
        <div class="min-w-0">
            <h4 class="fw-bold mb-0 text-truncate">{{ $title }}</h4>
            @if($subtitle)
            <p class="text-muted small mb-0 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
    <div class="d-flex align-items-center gap-2 flex-shrink-0">
        {{ $actions }}
    </div>
    @endisset
</div>
