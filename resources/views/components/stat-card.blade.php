@props([
    'label'   => '',
    'value'   => '—',
    'icon'    => 'bi-bar-chart-line',
    'color'   => 'primary',
    'trend'   => null,
    'trendUp' => null,
    'small'   => false,
    'href'    => null,
])

@php
$colors = [
    'emerald' => ['bg' => 'bg-success bg-opacity-10', 'text' => 'text-success'],
    'blue'    => ['bg' => 'bg-primary bg-opacity-10', 'text' => 'text-primary'],
    'green'   => ['bg' => 'bg-success bg-opacity-10', 'text' => 'text-success'],
    'purple'  => ['bg' => 'bg-purple bg-opacity-10', 'text' => 'text-purple'],
    'indigo'  => ['bg' => 'bg-primary bg-opacity-10', 'text' => 'text-primary'],
    'amber'   => ['bg' => 'bg-warning bg-opacity-10', 'text' => 'text-warning'],
    'red'     => ['bg' => 'bg-danger bg-opacity-10', 'text' => 'text-danger'],
    'orange'  => ['bg' => 'bg-warning bg-opacity-10', 'text' => 'text-warning'],
    'fuchsia' => ['bg' => 'bg-danger bg-opacity-10', 'text' => 'text-danger'],
    'slate'   => ['bg' => 'bg-secondary bg-opacity-10', 'text' => 'text-secondary'],
    'primary' => ['bg' => 'bg-primary bg-opacity-10', 'text' => 'text-primary'],
];

$c   = $colors[$color] ?? $colors['primary'];
$tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'card stat-card' . ($href ? ' card-hover text-decoration-none text-reset' : '')]) }}>
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-3">
            <div class="flex-grow-1 min-w-0">
                <p class="stat-label">{{ $label }}</p>
                <p class="stat-value {{ $small ? 'fs-5' : '' }}">{{ $value }}</p>
                @if($trend)
                <div class="mt-1">
                    @if($trendUp === true)
                    <span class="stat-trend stat-trend-up"><i class="bi bi-arrow-up-short"></i>{{ $trend }}</span>
                    @elseif($trendUp === false)
                    <span class="stat-trend stat-trend-down"><i class="bi bi-arrow-down-short"></i>{{ $trend }}</span>
                    @else
                    <span class="stat-trend stat-trend-flat">{{ $trend }}</span>
                    @endif
                </div>
                @endif
            </div>
            <div class="stat-icon {{ $c['bg'] }}">
                <i class="bi {{ $icon }} {{ $c['text'] }}"></i>
            </div>
        </div>
        {{ $slot ?? '' }}
    </div>
</{{ $tag }}>
