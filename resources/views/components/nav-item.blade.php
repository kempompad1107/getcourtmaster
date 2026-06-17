@props(['href', 'active' => false, 'icon' => 'bi-circle'])

<a href="{{ $href }}" class="nav-link {{ $active ? 'active' : '' }}">
    <span class="nav-icon"><i class="bi {{ $icon }}"></i></span>
    <span class="nav-label">{{ $slot }}</span>
</a>
