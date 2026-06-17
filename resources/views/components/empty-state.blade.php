@props([
    'title'       => 'No data yet',
    'description' => null,
    'icon'        => 'bi-inbox',
    'action'      => null,
    'actionLabel' => 'Get started',
])

<div {{ $attributes->merge(['class' => 'd-flex flex-column align-items-center justify-content-center py-5 px-4 text-center']) }}>
    <div class="empty-state-icon mb-3">
        <i class="bi {{ $icon }}"></i>
    </div>
    <h6 class="fw-semibold mb-1">{{ $title }}</h6>
    @if($description)
    <p class="text-muted small mb-0" style="max-width:280px">{{ $description }}</p>
    @endif
    @if($action)
    <div class="mt-4">
        <a href="{{ $action }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>{{ $actionLabel }}
        </a>
    </div>
    @endif
    @isset($slot)
    @if($slot->isNotEmpty())
    <div class="mt-4">{{ $slot }}</div>
    @endif
    @endisset
</div>
