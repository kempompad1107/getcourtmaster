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
    $totalPerms = collect($groups)->flatten()->count();
    $selectedNow = count(array_intersect(collect($groups)->flatten()->all(), old('permissions', $assigned)));
@endphp

@push('styles')
<style>
    .perm-scope { --pp-radius: 1rem; }

    /* ── Hero summary ───────────────────────────────────────────── */
    .perm-hero {
        position: relative; overflow: hidden;
        border-radius: 1.25rem;
        background:
            radial-gradient(120% 140% at 100% 0%, rgba(16,185,129,.18), transparent 55%),
            linear-gradient(180deg, var(--bs-card-bg), var(--bs-card-bg));
        border: 1px solid var(--bs-border-color);
        box-shadow: 0 18px 40px -24px rgba(15,38,67,.5);
    }
    .perm-hero::after {
        content: ""; position: absolute; inset: 0; pointer-events: none;
        background-image: radial-gradient(rgba(16,185,129,.10) 1px, transparent 1px);
        background-size: 16px 16px; opacity: .5; mask-image: linear-gradient(90deg, transparent, #000 60%);
    }
    .perm-hero__inner { position: relative; z-index: 1; }
    .perm-hero__badge {
        width: 56px; height: 56px; border-radius: 16px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1.5rem;
        color: #10b981; background: rgba(16,185,129,.12);
        border: 1px solid rgba(16,185,129,.28);
        box-shadow: inset 0 0 0 4px rgba(16,185,129,.06);
    }
    .perm-meter { height: 8px; border-radius: 99px; background: rgba(148,163,184,.22); overflow: hidden; }
    .perm-meter__fill {
        height: 100%; border-radius: 99px; width: 0;
        background: linear-gradient(90deg, #10b981, #34d399);
        box-shadow: 0 0 12px rgba(16,185,129,.55);
        transition: width .45s cubic-bezier(.22,1,.36,1);
    }
    .perm-chip-btn {
        border: 1px solid var(--bs-border-color); background: var(--bs-body-bg);
        color: var(--bs-body-color); border-radius: 99px; padding: .35rem .85rem;
        font-size: .78rem; font-weight: 600; display: inline-flex; align-items: center; gap: .35rem;
        transition: .18s ease;
    }
    .perm-chip-btn:hover { border-color: #10b981; color: #10b981; transform: translateY(-1px); }

    /* ── Group cards ────────────────────────────────────────────── */
    .perm-group {
        --hue: 160 84% 39%;
        border: 1px solid var(--bs-border-color); border-radius: var(--pp-radius);
        background: var(--bs-card-bg); overflow: hidden;
        transition: box-shadow .25s ease, transform .25s ease, border-color .25s ease;
        animation: permRise .5s cubic-bezier(.22,1,.36,1) both;
    }
    .perm-group:hover { box-shadow: 0 16px 34px -22px rgba(15,38,67,.55); border-color: hsl(var(--hue) / .4); }
    .perm-group__head {
        display: flex; align-items: center; gap: .75rem;
        padding: .9rem 1rem; border-bottom: 1px solid var(--bs-border-color);
        background: linear-gradient(180deg, hsl(var(--hue) / .06), transparent);
    }
    .perm-group__ico {
        width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1.05rem;
        color: hsl(var(--hue)); background: hsl(var(--hue) / .12);
        border: 1px solid hsl(var(--hue) / .25);
    }
    .perm-group__title { font-weight: 700; font-size: .95rem; line-height: 1.1; }
    .perm-group__count {
        font-size: .72rem; font-weight: 600; color: var(--bs-secondary-color);
        font-variant-numeric: tabular-nums;
    }
    .perm-group__count b { color: hsl(var(--hue)); }
    .perm-toggle-all {
        margin-left: auto; border: 0; background: transparent; cursor: pointer;
        font-size: .72rem; font-weight: 700; letter-spacing: .02em; text-transform: uppercase;
        color: var(--bs-secondary-color); display: inline-flex; align-items: center; gap: .3rem;
        padding: .3rem .55rem; border-radius: 8px; transition: .15s ease;
    }
    .perm-toggle-all:hover { color: hsl(var(--hue)); background: hsl(var(--hue) / .1); }

    .perm-group__body { padding: .5rem; display: grid; gap: .3rem; }

    /* ── Permission tile ────────────────────────────────────────── */
    .perm-tile {
        position: relative; display: flex; align-items: center; gap: .7rem;
        padding: .6rem .7rem; border-radius: .7rem; cursor: pointer; margin: 0;
        border: 1px solid transparent; transition: background .18s ease, border-color .18s ease;
    }
    .perm-tile:hover { background: var(--bs-tertiary-bg, rgba(148,163,184,.08)); }
    .perm-check { position: absolute; opacity: 0; pointer-events: none; }
    .perm-box {
        width: 22px; height: 22px; border-radius: 7px; flex-shrink: 0;
        border: 2px solid var(--bs-border-color); background: var(--bs-body-bg);
        display: grid; place-items: center; color: #fff;
        transition: background .18s ease, border-color .18s ease, transform .18s ease;
    }
    .perm-box .bi { font-size: .85rem; transform: scale(0); opacity: 0; transition: .2s cubic-bezier(.34,1.56,.64,1); }
    .perm-text { min-width: 0; }
    .perm-text__label { font-weight: 600; font-size: .88rem; line-height: 1.15; }
    .perm-text__key {
        font-size: .68rem; color: var(--bs-secondary-color);
        font-family: var(--bs-font-monospace); letter-spacing: -.01em;
    }

    .perm-check:checked ~ .perm-box {
        background: hsl(var(--hue)); border-color: hsl(var(--hue));
        box-shadow: 0 4px 10px -3px hsl(var(--hue) / .6);
    }
    .perm-check:checked ~ .perm-box .bi { transform: scale(1); opacity: 1; }
    .perm-tile:has(.perm-check:checked) {
        background: hsl(var(--hue) / .07);
        border-color: hsl(var(--hue) / .25);
    }
    .perm-check:focus-visible ~ .perm-box {
        outline: 2px solid hsl(var(--hue)); outline-offset: 2px;
    }

    /* ── Sticky save bar ────────────────────────────────────────── */
    .perm-savebar {
        position: sticky; bottom: 1rem; z-index: 20; margin-top: 1.5rem;
        border-radius: 1rem; padding: .75rem 1rem;
        display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        background: color-mix(in srgb, var(--bs-card-bg) 86%, transparent);
        backdrop-filter: blur(12px) saturate(140%);
        border: 1px solid var(--bs-border-color);
        box-shadow: 0 22px 50px -26px rgba(15,38,67,.7);
    }
    .perm-savebar__count {
        font-variant-numeric: tabular-nums; font-weight: 800; font-size: 1.05rem;
        color: var(--bs-emphasis-color);
    }

    @keyframes permRise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) {
        .perm-group, .perm-meter__fill, .perm-box .bi { animation: none !important; transition: none !important; }
    }
</style>
@endpush

@section('content')

<x-page-header :title="'Edit Role: ' . ucwords(str_replace('_', ' ', $role->name))"
               :back="route('admin.roles.index')"/>

<div class="row justify-content-center perm-scope">
    <div class="col-12 col-xxl-11">

        <form method="POST" action="{{ route('admin.roles.update', $role) }}" id="permForm">
            @csrf @method('PUT')

            {{-- ── Hero summary ─────────────────────────────────────── --}}
            <div class="perm-hero mb-4">
                <div class="perm-hero__inner p-3 p-md-4 d-flex flex-column flex-md-row align-items-md-center gap-3">
                    <div class="perm-hero__badge"><i class="bi bi-shield-lock-fill"></i></div>

                    <div class="flex-grow-1 w-100">
                        <h5 class="mb-1 fw-bold">{{ ucwords(str_replace('_', ' ', $role->name)) }} access</h5>
                        <p class="mb-2 small text-muted" style="max-width: 46rem;">
                            Tick the permissions this role should have. The sidebar and page actions update
                            automatically for every staff member with this role.
                        </p>
                        <div class="d-flex align-items-center gap-3">
                            <div class="perm-meter flex-grow-1" style="max-width: 22rem;">
                                <div class="perm-meter__fill" id="permMeter"></div>
                            </div>
                            <span class="small fw-semibold text-nowrap">
                                <span id="permTotalInline" class="text-primary">{{ $selectedNow }}</span>
                                <span class="text-muted">/ {{ $totalPerms }} enabled</span>
                            </span>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-shrink-0">
                        <button type="button" class="perm-chip-btn" data-master="all">
                            <i class="bi bi-check2-all"></i>Select all
                        </button>
                        <button type="button" class="perm-chip-btn" data-master="none">
                            <i class="bi bi-x-lg"></i>Clear
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── Permission groups ────────────────────────────────── --}}
            <div class="row g-3">
                @foreach($groups as $groupLabel => $perms)
                    @php $meta = $groupMeta[$groupLabel] ?? ['icon' => 'bi-key', 'hue' => '160 84% 39%']; @endphp
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="perm-group h-100" data-group
                             style="--hue: {{ $meta['hue'] }}; animation-delay: {{ $loop->index * 55 }}ms;">
                            <div class="perm-group__head">
                                <span class="perm-group__ico"><i class="bi {{ $meta['icon'] }}"></i></span>
                                <div>
                                    <div class="perm-group__title">{{ $groupLabel }}</div>
                                    <div class="perm-group__count">
                                        <b data-grp-current>0</b> of {{ count($perms) }} enabled
                                    </div>
                                </div>
                                <button type="button" class="perm-toggle-all" data-toggle-all>
                                    <i class="bi bi-check2-square"></i><span>All</span>
                                </button>
                            </div>
                            <div class="perm-group__body">
                                @foreach($perms as $perm)
                                    @php
                                        $action = explode('.', $perm)[1] ?? $perm;
                                        $label = ucwords(str_replace(['_', '.'], ' ', $action));
                                    @endphp
                                    <label class="perm-tile">
                                        <input type="checkbox" class="perm-check"
                                               name="permissions[]" value="{{ $perm }}"
                                               @checked(in_array($perm, old('permissions', $assigned)))>
                                        <span class="perm-box"><i class="bi bi-check-lg"></i></span>
                                        <span class="perm-text">
                                            <span class="perm-text__label d-block text-truncate">{{ $label }}</span>
                                            <span class="perm-text__key d-block text-truncate">{{ $perm }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ── Sticky save bar ──────────────────────────────────── --}}
            <div class="perm-savebar">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-fingerprint text-primary fs-5"></i>
                    <div class="lh-1">
                        <span class="perm-savebar__count"><span id="permTotalBar">{{ $selectedNow }}</span></span>
                        <span class="text-muted small">of {{ $totalPerms }} permissions enabled</span>
                    </div>
                </div>
                <div class="ms-auto d-flex gap-2">
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg me-1"></i>Save Permissions
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
        const btn = group.querySelector('[data-toggle-all] span');
        if (btn) btn.textContent = (checked === boxes.length && boxes.length) ? 'None' : 'All';
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

    // Per-group toggle-all
    document.querySelectorAll('[data-toggle-all]').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.closest('[data-group]');
            const boxes = group.querySelectorAll('.perm-check');
            const fill  = Array.from(boxes).some(b => !b.checked);
            boxes.forEach(b => b.checked = fill);
            refreshAll();
        });
    });

    // Master select all / clear
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
