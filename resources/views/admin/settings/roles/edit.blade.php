@extends('layouts.app')
@section('title', 'Edit Role: ' . ucwords(str_replace('_', ' ', $role->name)))

@php
    $groupMeta = [
        'Branches'    => ['icon' => 'bi-diagram-3',      'hue' => '199 89% 48%'],
        'Courts'      => ['icon' => 'bi-grid-3x3-gap',   'hue' => '262 83% 58%'],
        'Bookings'    => ['icon' => 'bi-calendar-check', 'hue' => '160 84% 39%'],
        'POS'         => ['icon' => 'bi-bag-check',      'hue' => '24 95% 53%'],
        'Memberships' => ['icon' => 'bi-card-heading',   'hue' => '330 81% 60%'],
        'Customers'   => ['icon' => 'bi-people',         'hue' => '199 89% 48%'],
        'Inventory'   => ['icon' => 'bi-box-seam',       'hue' => '38 92% 50%'],
        'Promotions'  => ['icon' => 'bi-tag',            'hue' => '291 64% 52%'],
        'Reports'     => ['icon' => 'bi-graph-up-arrow', 'hue' => '173 80% 40%'],
        'Tournaments' => ['icon' => 'bi-trophy',         'hue' => '43 96% 48%'],
        'Staff'       => ['icon' => 'bi-person-gear',    'hue' => '215 90% 54%'],
    ];
    $totalPerms  = collect($groups)->flatten()->count();
    $selectedNow = count(array_intersect(collect($groups)->flatten()->all(), old('permissions', $assigned)));
@endphp

@push('styles')
<style>
    .perm-scope { --pp-radius: 1rem; }

    /* ── Hero ─────────────────────────────────────────────────────── */
    .perm-hero {
        position: relative; overflow: hidden; border-radius: 1.25rem;
        background:
            radial-gradient(120% 140% at 100% 0%, rgba(16,185,129,.18), transparent 55%),
            linear-gradient(180deg, var(--bs-card-bg), var(--bs-card-bg));
        border: 1px solid var(--bs-border-color);
        box-shadow: 0 18px 40px -24px rgba(15,38,67,.5);
    }
    .perm-hero::after {
        content: ""; position: absolute; inset: 0; pointer-events: none;
        background-image: radial-gradient(rgba(16,185,129,.10) 1px, transparent 1px);
        background-size: 16px 16px; opacity: .5;
        mask-image: linear-gradient(90deg, transparent, #000 60%);
    }
    .perm-hero__inner { position: relative; z-index: 1; }
    .perm-hero__badge {
        width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1.4rem;
        color: #10b981; background: rgba(16,185,129,.12);
        border: 1px solid rgba(16,185,129,.28);
    }
    .perm-meter { height: 8px; border-radius: 99px; background: rgba(148,163,184,.22); overflow: hidden; }
    .perm-meter__fill {
        height: 100%; border-radius: 99px; width: 0;
        background: linear-gradient(90deg, #10b981, #34d399);
        box-shadow: 0 0 12px rgba(16,185,129,.55);
        transition: width .45s cubic-bezier(.22,1,.36,1);
    }

    /* ── Group cards ──────────────────────────────────────────────── */
    .perm-group {
        --hue: 160 84% 39%;
        border: 1px solid var(--bs-border-color); border-radius: var(--pp-radius);
        background: var(--bs-card-bg); overflow: hidden;
        transition: box-shadow .25s ease, border-color .25s ease;
        animation: permRise .4s cubic-bezier(.22,1,.36,1) both;
    }
    .perm-group:hover { box-shadow: 0 16px 34px -22px rgba(15,38,67,.5); border-color: hsl(var(--hue) / .4); }
    .perm-group__head {
        display: flex; align-items: center; gap: .75rem;
        padding: .85rem 1rem; border-bottom: 1px solid var(--bs-border-color);
        background: linear-gradient(180deg, hsl(var(--hue) / .06), transparent);
    }
    .perm-group__ico {
        width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1rem;
        color: hsl(var(--hue)); background: hsl(var(--hue) / .12);
        border: 1px solid hsl(var(--hue) / .25);
    }
    .perm-group__title { font-weight: 700; font-size: .92rem; }
    .perm-group__count { font-size: .72rem; font-weight: 600; color: var(--bs-secondary-color); }
    .perm-group__count b { color: hsl(var(--hue)); }
    .perm-group__body { padding: .5rem; display: grid; gap: .25rem; }

    /* ── Permission tile ──────────────────────────────────────────── */
    .perm-tile {
        position: relative; display: flex; align-items: center; gap: .65rem;
        padding: .55rem .65rem; border-radius: .65rem; cursor: pointer; margin: 0;
        border: 1px solid transparent; transition: background .18s, border-color .18s;
    }
    .perm-tile:hover { background: var(--bs-tertiary-bg, rgba(148,163,184,.08)); }
    .perm-check { position: absolute; opacity: 0; pointer-events: none; }
    .perm-box {
        width: 20px; height: 20px; border-radius: 6px; flex-shrink: 0;
        border: 2px solid var(--bs-border-color); background: var(--bs-body-bg);
        display: grid; place-items: center; color: #fff;
        transition: background .18s, border-color .18s, transform .18s;
    }
    .perm-box .bi { font-size: .8rem; transform: scale(0); opacity: 0; transition: .2s cubic-bezier(.34,1.56,.64,1); }
    .perm-label { font-weight: 600; font-size: .85rem; line-height: 1.2; }

    .perm-check:checked ~ .perm-box {
        background: hsl(var(--hue)); border-color: hsl(var(--hue));
        box-shadow: 0 3px 8px -2px hsl(var(--hue) / .55);
    }
    .perm-check:checked ~ .perm-box .bi { transform: scale(1); opacity: 1; }
    .perm-tile:has(.perm-check:checked) {
        background: hsl(var(--hue) / .07); border-color: hsl(var(--hue) / .25);
    }
    .perm-check:focus-visible ~ .perm-box { outline: 2px solid hsl(var(--hue)); outline-offset: 2px; }

    /* ── Sticky save bar ──────────────────────────────────────────── */
    .perm-savebar {
        position: sticky; bottom: 1rem; z-index: 20; margin-top: 1.5rem;
        border-radius: 1rem; padding: .85rem 1.25rem;
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        background: var(--bs-card-bg);
        backdrop-filter: blur(12px) saturate(140%);
        border: 1px solid var(--bs-border-color);
        box-shadow: 0 22px 50px -26px rgba(15,38,67,.7);
    }
    .perm-savebar__count { font-variant-numeric: tabular-nums; font-weight: 800; font-size: 1rem; }

    @keyframes permRise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) {
        .perm-group, .perm-meter__fill, .perm-box .bi { animation: none !important; transition: none !important; }
    }
    /* Push save bar above mobile bottom nav (58px) */
    @media (max-width: 991.98px) {
        .perm-savebar { bottom: calc(58px + env(safe-area-inset-bottom) + .5rem); }
    }
</style>
@endpush

@section('content')

<x-page-header :title="'Edit: ' . ucwords(str_replace('_', ' ', $role->name))"
               :back="route('admin.roles.index')"/>

<div class="row justify-content-center perm-scope">
    <div class="col-12 col-xxl-11">

        <form method="POST" action="{{ route('admin.roles.update', $role) }}" id="permForm">
            @csrf @method('PUT')

            {{-- ── Hero ────────────────────────────────────────────── --}}
            <div class="perm-hero mb-4">
                <div class="perm-hero__inner p-3 p-md-4">

                    {{-- Top row: badge + title + master buttons --}}
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="perm-hero__badge"><i class="bi bi-shield-lock-fill"></i></div>
                        <div class="flex-grow-1 min-w-0">
                            <h5 class="mb-0 fw-bold">{{ ucwords(str_replace('_', ' ', $role->name)) }}</h5>
                            <p class="mb-0 small text-muted d-none d-md-block">
                                Tick the permissions this role should have. Changes apply immediately.
                            </p>
                        </div>
                        <div class="d-flex gap-2 flex-shrink-0">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-master="all">
                                <i class="bi bi-check2-all me-1"></i><span class="d-none d-sm-inline">Select all</span><span class="d-sm-none">All</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-master="none">
                                <i class="bi bi-x-lg me-1"></i><span class="d-none d-sm-inline">Clear</span>
                            </button>
                        </div>
                    </div>

                    {{-- Bottom row: progress meter --}}
                    <div class="d-flex align-items-center gap-3">
                        <div class="perm-meter flex-grow-1">
                            <div class="perm-meter__fill" id="permMeter"></div>
                        </div>
                        <span class="small fw-semibold text-nowrap flex-shrink-0">
                            <span id="permTotalInline" class="text-success">{{ $selectedNow }}</span>
                            <span class="text-muted">/ {{ $totalPerms }} enabled</span>
                        </span>
                    </div>

                </div>
            </div>

            {{-- ── Permission groups ─────────────────────────────── --}}
            <div class="row g-3">
                @foreach($groups as $groupLabel => $perms)
                    @php $meta = $groupMeta[$groupLabel] ?? ['icon' => 'bi-key', 'hue' => '160 84% 39%']; @endphp
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="perm-group h-100" data-group
                             style="--hue: {{ $meta['hue'] }}; animation-delay: {{ min($loop->index * 40, 200) }}ms;">
                            <div class="perm-group__head">
                                <span class="perm-group__ico"><i class="bi {{ $meta['icon'] }}"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="perm-group__title">{{ $groupLabel }}</div>
                                    <div class="perm-group__count"><b data-grp-current>0</b> of {{ count($perms) }} enabled</div>
                                </div>
                                <button type="button" class="btn btn-link btn-sm text-muted p-1 ms-1 flex-shrink-0" data-toggle-all
                                        title="Toggle all {{ $groupLabel }} permissions">
                                    <i class="bi bi-check2-square"></i>
                                </button>
                            </div>
                            <div class="perm-group__body">
                                @foreach($perms as $perm)
                                    @php $label = ucwords(str_replace(['_', '.'], ' ', explode('.', $perm)[1] ?? $perm)); @endphp
                                    <label class="perm-tile">
                                        <input type="checkbox" class="perm-check"
                                               name="permissions[]" value="{{ $perm }}"
                                               @checked(in_array($perm, old('permissions', $assigned)))>
                                        <span class="perm-box"><i class="bi bi-check-lg"></i></span>
                                        <span class="perm-label">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ── Sticky save bar ────────────────────────────────── --}}
            <div class="perm-savebar">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-fingerprint text-success fs-5"></i>
                    <div class="lh-1">
                        <span class="perm-savebar__count"><span id="permTotalBar">{{ $selectedNow }}</span> <span class="fs-6 fw-normal text-muted">of {{ $totalPerms }} enabled</span></span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save
                    </button>
                </div>
            </div>

        </form>

    </div>
</div>

@push('scripts')
<script>
(function () {
    const form   = document.getElementById('permForm');
    const total  = {{ $totalPerms }};
    const meter  = document.getElementById('permMeter');
    const inline = document.getElementById('permTotalInline');
    const bar    = document.getElementById('permTotalBar');

    function refreshGroup(group) {
        const boxes   = group.querySelectorAll('.perm-check');
        const checked = group.querySelectorAll('.perm-check:checked').length;
        group.querySelector('[data-grp-current]').textContent = checked;
    }

    function refreshTotals() {
        const checked = form.querySelectorAll('.perm-check:checked').length;
        inline.textContent = checked;
        bar.textContent = checked;
        meter.style.width = total ? (checked / total * 100) + '%' : '0%';
    }

    function refreshAll() {
        document.querySelectorAll('[data-group]').forEach(refreshGroup);
        refreshTotals();
    }

    document.querySelectorAll('[data-toggle-all]').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.closest('[data-group]');
            const boxes = group.querySelectorAll('.perm-check');
            const fill  = Array.from(boxes).some(b => !b.checked);
            boxes.forEach(b => b.checked = fill);
            refreshAll();
        });
    });

    document.querySelectorAll('[data-master]').forEach(btn => {
        btn.addEventListener('click', () => {
            const on = btn.dataset.master === 'all';
            form.querySelectorAll('.perm-check').forEach(b => b.checked = on);
            refreshAll();
        });
    });

    form.addEventListener('change', e => {
        if (e.target.classList.contains('perm-check')) refreshAll();
    });

    refreshAll();
})();
</script>
@endpush

@endsection
