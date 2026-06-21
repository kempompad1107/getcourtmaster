@props([
    'title',
    'subtitle' => null,
    'back'     => null,
    'backLabel'=> 'Back',
])

<div class="d-flex align-items-start justify-content-between gap-3 pb-3 mb-4 border-bottom">
    <div class="d-flex align-items-center gap-3 min-w-0">
        @if($back)
        <a href="{{ $back }}" class="d-inline-flex align-items-center gap-1 text-muted text-decoration-none flex-shrink-0"
           style="font-size:.8rem">
            <i class="bi bi-arrow-left" style="font-size:.75rem"></i>
            {{ $backLabel }}
        </a>
        <span class="text-muted" style="opacity:.35">|</span>
        @endif
        <div class="min-w-0">
            <h4 class="fw-bold mb-0 text-truncate">{{ $title }}</h4>
            @if($subtitle)
            <p class="text-muted small mb-0 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
    <div class="ph-actions d-flex align-items-center gap-2 flex-wrap">
        {{ $actions }}
    </div>
    @endisset
</div>
