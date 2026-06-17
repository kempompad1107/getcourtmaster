@props(['status'])
@php
$map = [
    'draft'               => ['neutral',   'Draft'],
    'registration_open'   => ['active',    'Registration Open'],
    'registration_closed' => ['pending',   'Registration Closed'],
    'ongoing'             => ['info',      'Ongoing'],
    'completed'           => ['completed', 'Completed'],
    'cancelled'           => ['cancelled', 'Cancelled'],
];
[$badge, $label] = $map[$status] ?? ['neutral', ucfirst(str_replace('_', ' ', $status))];
@endphp
<x-badge :status="$badge">{{ $label }}</x-badge>
