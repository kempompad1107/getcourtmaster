@props([
    'title'    => null,
    'subtitle' => null,
    'padding'  => true,
    'flush'    => false,
    'hover'    => false,
])

<div {{ $attributes->merge(['class' => 'card' . ($hover ? ' card-hover' : '')]) }}>
    @if($title)
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h6 class="mb-0 fw-semibold">{{ $title }}</h6>
            @if($subtitle)
            <small class="text-muted">{{ $subtitle }}</small>
            @endif
        </div>
        @isset($actions)
        <div class="d-flex align-items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
    @endif

    @if($flush)
    {{ $slot }}
    @else
    <div class="{{ $padding ? 'card-body' : 'p-0' }}">{{ $slot }}</div>
    @endif

    @isset($footer)
    <div class="card-footer">{{ $footer }}</div>
    @endisset
</div>
