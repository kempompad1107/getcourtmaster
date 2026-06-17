@extends('layouts.app')
@section('title', 'Branches')

@push('styles')
<style>
    /* ── Branches — responsive card grid over the admin theme ── */
    .branch-card {
        position: relative; height: 100%; overflow: hidden;
        border: 1px solid var(--bs-border-color);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .branch-card:hover {
        transform: translateY(-3px);
        border-color: rgba(16,185,129,.35);
        box-shadow: 0 16px 32px -22px rgba(0,0,0,.55);
    }
    .branch-card.is-main { border-color: rgba(16,185,129,.4); }
    .branch-card.is-main::before {
        content: ""; position: absolute; inset: 0 0 auto 0; height: 3px;
        background: linear-gradient(90deg, #34d399, rgba(16,185,129,.15));
    }

    /* Monogram avatar — visual anchor per branch */
    .branch-monogram {
        width: 46px; height: 46px; flex-shrink: 0;
        border-radius: .85rem;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.05rem; letter-spacing: -.02em;
        color: var(--bs-secondary-color);
        background: var(--bs-body-bg-alt, rgba(148,163,184,.08));
        border: 1px solid var(--bs-border-color);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.02);
    }
    .branch-card.is-main .branch-monogram {
        color: #fff;
        background: linear-gradient(135deg, #10b981, #047857);
        border-color: rgba(16,185,129,.5);
        box-shadow: 0 6px 16px -8px rgba(16,185,129,.6);
    }

    .branch-name { font-size: 1.1rem; font-weight: 700; letter-spacing: -.01em; margin: 0; line-height: 1.2; }
    .branch-slug { font-family: ui-monospace, monospace; font-size: .72rem; color: var(--bs-secondary-color); }
    .branch-meta { font-size: .85rem; color: var(--bs-secondary-color); }
    .branch-meta i { width: 1rem; color: var(--bs-secondary-color); opacity: .8; }
    .branch-stats { display: flex; gap: .5rem; flex-wrap: wrap; }
    .branch-stat {
        flex: 1 1 0; min-width: 0;
        display: flex; flex-direction: column; gap: .1rem;
        padding: .55rem .7rem; border-radius: .7rem;
        background: var(--bs-body-bg-alt, rgba(148,163,184,.06));
        border: 1px solid var(--bs-border-color);
    }
    .branch-stat .n { font-weight: 800; font-size: 1.15rem; line-height: 1; font-variant-numeric: tabular-nums; color: var(--bs-heading-color); }
    .branch-stat .l { font-size: .62rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--bs-secondary-color); }
    .branch-card .card-footer { background: transparent; border-top: 1px solid var(--bs-border-color); }

    /* Compact summary strip */
    .branch-summary .stat-card .card-body { padding: 1rem 1.1rem; }
</style>
@endpush

@section('content')

<x-page-header title="Branches"
    :subtitle="$branches->total() . ' ' . str('branch')->plural($branches->total())">
    <x-slot name="actions">
        @can('create', App\Models\Branch::class)
        @php $branchLimit = app(\App\Services\PlanLimitGuard::class)->check(auth()->user()->tenant, 'branches'); @endphp
        @if($branchLimit['allowed'])
            <a href="{{ route('admin.branches.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Add Branch
            </a>
        @else
            <button class="btn btn-primary btn-sm" disabled title="Plan limit reached ({{ $branchLimit['used'] }}/{{ $branchLimit['max'] }} on {{ $branchLimit['plan'] }})">
                <i class="bi bi-lock-fill me-1"></i>Add Branch
            </button>
        @endif
        @endcan
    </x-slot>
</x-page-header>

@include('admin._partials.plan-limit-banner', ['resource' => 'branches'])

{{-- Network summary --}}
<div class="branch-summary row row-cols-2 row-cols-lg-4 g-3 mb-4">
    <div class="col">
        <x-stat-card label="Branches" :value="$stats['total']" icon="bi-shop" color="emerald" small />
    </div>
    <div class="col">
        <x-stat-card label="Active" :value="$stats['active']" icon="bi-check-circle" color="green" small>
            @if($stats['inactive'])
                <p class="small text-muted mb-0 mt-1">{{ $stats['inactive'] }} inactive</p>
            @endif
        </x-stat-card>
    </div>
    <div class="col">
        <x-stat-card label="Courts" :value="$stats['courts']" icon="bi-grid-3x3-gap" color="blue" small />
    </div>
    <div class="col">
        <x-stat-card label="Staff" :value="$stats['staff']" icon="bi-people" color="amber" small />
    </div>
</div>

{{-- Unified filter bar: search + Filters popover --}}
<x-filter-bar placeholder="Search by name or city…"
              :active-count="(int) request()->filled('status')"
              :clear="route('admin.branches.index')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <option value="active"   @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
    </x-slot>
</x-filter-bar>

@if($branches->isEmpty())
    <x-empty-state
        title="No branches yet"
        description="Branches let you organise courts, staff and POS terminals by physical location."
        icon="bi-shop"
        @can('create', App\Models\Branch::class)
        action="{{ route('admin.branches.create') }}"
        actionLabel="Add your first branch"
        @endcan/>
@else
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
    @foreach($branches as $branch)
        @php
            $signupUrl = route('register.tenant', ['tenant' => auth()->user()->tenant->slug])
                       . '?branch=' . $branch->id;
            $initials = \Illuminate\Support\Str::of($branch->name)
                ->explode(' ')->filter()->take(2)
                ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        @endphp
        <div class="col">
            <div class="card branch-card {{ $branch->is_main ? 'is-main' : '' }}">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3 mb-1">
                        <div class="branch-monogram">{{ $initials ?: '–' }}</div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <h6 class="branch-name text-truncate">{{ $branch->name }}</h6>
                                <x-badge :status="$branch->is_active ? 'active' : 'expired'" class="flex-shrink-0">{{ $branch->is_active ? 'Active' : 'Inactive' }}</x-badge>
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <span class="branch-slug text-truncate">{{ $branch->slug }}</span>
                                @if($branch->is_main)
                                    <span class="badge bg-primary-subtle text-primary rounded-pill flex-shrink-0">Main</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="branch-meta mt-3">
                        <div class="d-flex gap-2">
                            <i class="bi bi-geo-alt mt-1"></i>
                            <span>
                                @if($branch->address || $branch->city)
                                    {{ $branch->address }}@if($branch->address && $branch->city), @endif{{ $branch->city }}
                                @else
                                    <span class="text-muted">No address set</span>
                                @endif
                            </span>
                        </div>
                        @if($branch->phone)
                            <div class="d-flex gap-2 mt-1"><i class="bi bi-telephone"></i><span>{{ $branch->phone }}</span></div>
                        @endif
                        @if($branch->email)
                            <div class="d-flex gap-2 mt-1"><i class="bi bi-envelope"></i><span class="text-truncate">{{ $branch->email }}</span></div>
                        @endif
                    </div>

                    <div class="branch-stats mt-3">
                        <span class="branch-stat"><span class="n">{{ $branch->courts_count }}</span><span class="l">Courts</span></span>
                        <span class="branch-stat"><span class="n">{{ $branch->staff_count }}</span><span class="l">Staff</span></span>
                    </div>
                </div>

                <div class="card-footer d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm"
                            data-bs-toggle="modal" data-bs-target="#branchQrModal"
                            data-branch-name="{{ $branch->name }}"
                            data-signup-url="{{ $signupUrl }}"
                            title="Customer signup QR">
                        <i class="bi bi-qr-code me-1"></i>QR
                    </button>
                    @can('update', $branch)
                    <a href="{{ route('admin.branches.edit', $branch) }}"
                       class="btn btn-outline-secondary btn-sm flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    @endcan
                    @can('delete', $branch)
                    <form method="POST" action="{{ route('admin.branches.destroy', $branch) }}"
                          onsubmit="return confirm('Delete branch {{ $branch->name }}?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete branch">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
    @endforeach
</div>

@if($branches->hasPages())
<div class="d-flex justify-content-center mt-4">
    {{ $branches->links() }}
</div>
@endif
@endif

{{-- Signup QR modal: rendered into the @stack('modals') outlet so it escapes
     the page-enter transform context that otherwise traps the backdrop and
     blocks pointer events. --}}
@push('modals')
<div class="modal fade" id="branchQrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">
                    <i class="bi bi-qr-code me-1 text-success"></i>
                    Customer signup &mdash; <span id="qrBranchName"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted small mb-3">
                    Print this and put it at the front desk, or share the link.
                    New signups via this QR will land on <strong id="qrBranchNameInline"></strong> as their home branch.
                </p>
                <img id="qrImage" src="" alt="Signup QR code"
                     class="img-fluid border rounded mb-3"
                     style="max-width:280px;background:#fff;padding:10px">

                <div class="input-group input-group-sm mb-2">
                    <input type="text" id="qrUrl" class="form-control font-monospace" readonly>
                    <button type="button" class="btn btn-outline-secondary" onclick="
                        const i = document.getElementById('qrUrl'); i.select(); document.execCommand('copy');
                        this.innerHTML = '<i class=\'bi bi-check2\'></i> Copied';
                        setTimeout(() => this.innerHTML = '<i class=\'bi bi-clipboard\'></i> Copy', 1500);
                    "><i class="bi bi-clipboard"></i> Copy</button>
                </div>

                <div class="d-flex justify-content-center gap-2">
                    <a id="qrPrint" href="#" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-1"></i>Open / Print QR
                    </a>
                    <a id="qrVisit" href="#" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Preview signup page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('branchQrModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', (event) => {
        const btn  = event.relatedTarget;
        const name = btn?.getAttribute('data-branch-name') ?? '';
        const url  = btn?.getAttribute('data-signup-url')  ?? '';
        const qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=12&data=' + encodeURIComponent(url);

        document.getElementById('qrBranchName').textContent = name;
        document.getElementById('qrBranchNameInline').textContent = name;
        document.getElementById('qrImage').src = qrSrc;
        document.getElementById('qrUrl').value = url;
        document.getElementById('qrPrint').href = qrSrc;
        document.getElementById('qrVisit').href = url;
    });
});
</script>
@endpush

@endsection
