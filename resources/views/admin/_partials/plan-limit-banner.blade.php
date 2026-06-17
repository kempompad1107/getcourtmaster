@props(['resource', 'label' => null])
@php
    /** @var array $check */
    $check = app(\App\Services\PlanLimitGuard::class)->check(auth()->user()->tenant, $resource);
    $label = $label ?? ucfirst($resource);
    $dismissed = session('plan_banner_dismissed', [])[$resource] ?? false;
@endphp

@unless($dismissed)
    @if(! $check['allowed'])
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3 py-2 js-dismissible-banner" data-key="{{ $resource }}">
        <i class="bi bi-shield-lock-fill"></i>
        <div class="flex-grow-1 small">
            <strong>{{ $label }} limit reached</strong> — {{ $check['used'] }} / {{ $check['max'] }} on the {{ $check['plan'] }} plan.
            Upgrade your subscription to add more.
        </div>
        <button type="button" class="btn-close" aria-label="Close"></button>
    </div>
    @elseif(isset($check['pct']) && $check['pct'] >= 80)
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3 py-2 js-dismissible-banner" data-key="{{ $resource }}">
        <i class="bi bi-exclamation-triangle"></i>
        <div class="flex-grow-1 small">
            {{ $label }} usage at <strong>{{ $check['pct'] }}%</strong> ({{ $check['used'] }} / {{ $check['max'] }}) on the {{ $check['plan'] }} plan.
        </div>
        <button type="button" class="btn-close" aria-label="Close"></button>
    </div>
    @endif

    @include('admin._partials.dismissible-banner-script')
@endunless
