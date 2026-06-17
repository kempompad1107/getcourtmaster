@props([
    'type'        => 'info',
    'dismissible' => false,
    'title'       => null,
])

@php
$types = [
    'success' => 'alert-success',
    'error'   => 'alert-danger',
    'danger'  => 'alert-danger',
    'warning' => 'alert-warning',
    'info'    => 'alert-info',
];

$icons = [
    'success' => 'bi-check-circle-fill',
    'error'   => 'bi-exclamation-triangle-fill',
    'danger'  => 'bi-exclamation-triangle-fill',
    'warning' => 'bi-exclamation-circle-fill',
    'info'    => 'bi-info-circle-fill',
];

$alertClass = $types[$type] ?? 'alert-info';
$iconClass  = $icons[$type] ?? 'bi-info-circle-fill';
@endphp

<div {{ $attributes->merge(['class' => 'alert ' . $alertClass . ($dismissible ? ' alert-dismissible fade show' : '') . ' d-flex align-items-start gap-2']) }}
     role="alert"
     @if($dismissible) x-data="{ show: true }" x-show="show" @endif>
    <i class="bi {{ $iconClass }} flex-shrink-0 mt-1"></i>
    <div class="flex-grow-1">
        @if($title)
        <strong class="d-block mb-1">{{ $title }}</strong>
        @endif
        {{ $slot }}
    </div>
    @if($dismissible)
    <button type="button" class="btn-close" @click="show = false" aria-label="Close"></button>
    @endif
</div>
