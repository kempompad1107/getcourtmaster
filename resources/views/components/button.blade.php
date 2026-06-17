@props([
    'variant' => 'primary',
    'size'    => 'md',
    'href'    => null,
    'type'    => 'button',
    'disabled'=> false,
    'loading' => false,
    'icon'    => false,
])

@php
$variants = [
    'primary'         => 'btn-primary',
    'secondary'       => 'btn-outline-secondary',
    'ghost'           => 'btn-outline-secondary',
    'danger'          => 'btn-outline-danger',   // default destructive = outline (kit)
    'danger-solid'    => 'btn-danger',           // reserve solid for confirm-modals
    'warning'         => 'btn-warning',
    'success'         => 'btn-success',
    'info'            => 'btn-info',
    'outline'         => 'btn-outline-primary',
    'outline-primary' => 'btn-outline-primary',
    'outline-danger'  => 'btn-outline-danger',
    'soft'            => 'btn-outline-secondary',
    'link'            => 'btn-link',
];

$sizes = [
    'xs' => 'btn-sm',
    'sm' => 'btn-sm',
    'md' => '',
    'lg' => 'btn-lg',
    'xl' => 'btn-lg',
];

$cls = trim('btn ' . ($variants[$variant] ?? 'btn-primary') . ' ' . ($sizes[$size] ?? ''));
$tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    @if(!$href) type="{{ $type }}" @endif
    @if($disabled || $loading) disabled @endif
    {{ $attributes->merge(['class' => $cls]) }}>
    @if($loading)
    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
    @elseif($icon)
    {{ $icon }}
    @endif
    {{ $slot }}
</{{ $tag }}>
