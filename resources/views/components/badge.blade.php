@props([
    'color'  => 'secondary',
    'status' => null,   // active|confirmed|pending|cancelled|denied|no_show|expired|completed|info|neutral → soft-tint + dot
    'dot'    => false,
    'size'   => 'md',
])

@php
$statusMap = [
    'active'    => 'badge-status badge-st-active',
    'confirmed' => 'badge-status badge-st-active',
    'pending'   => 'badge-status badge-st-pending',
    'cancelled' => 'badge-status badge-st-cancelled',
    'denied'    => 'badge-status badge-st-cancelled',
    'no_show'   => 'badge-status badge-st-cancelled',
    'expired'   => 'badge-status badge-st-expired',
    'completed' => 'badge-status badge-st-completed',
    'info'      => 'badge-status badge-st-info',
    'neutral'   => 'badge-status badge-st-neutral',
];

$colors = [
    'gray'      => 'text-bg-secondary',
    'green'     => 'text-bg-success',
    'emerald'   => 'text-bg-success',
    'blue'      => 'text-bg-primary',
    'indigo'    => 'text-bg-primary',
    'purple'    => 'badge-soft-purple',
    'fuchsia'   => 'badge-soft-pink',
    'pink'      => 'badge-soft-pink',
    'red'       => 'text-bg-danger',
    'orange'    => 'badge-soft-orange',
    'amber'     => 'badge-soft-warning',
    'yellow'    => 'text-bg-warning text-dark',
    'secondary' => 'text-bg-secondary',
];

if ($status !== null) {
    $cls = 'badge ' . ($statusMap[$status] ?? 'badge-status badge-st-neutral');
} else {
    $cls = 'badge ' . ($colors[$color] ?? 'text-bg-secondary');
}
@endphp

<span {{ $attributes->merge(['class' => $cls]) }}>
    @if($dot && $status === null)
    <span class="badge-dot me-1"></span>
    @endif
    {{ $slot }}
</span>
