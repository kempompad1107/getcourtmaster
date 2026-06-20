@extends('layouts.app')
@section('title', 'Branches')

@push('styles')
<style>
    /* ── Branches ── */
    .branch-card {
        position: relative; height: 100%; overflow: hidden;
        border: 1px solid var(--bs-border-color);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .branch-card:hover {
        transform: translateY(-3px);
        border-color: rgba(16,185,129,.35);
        box-shadow: 0 16px 32px -22px rgba(0,0,0,.45);
    }
    .branch-card.is-main { border-color: rgba(16,185,129,.4); }

    /* Coloured header band */
    .branch-card-header {
        padding: 1.1rem 1.25rem .9rem;
        background: var(--bs-body-bg-alt, rgba(148,163,184,.04));
        border-bottom: 1px solid var(--bs-border-color);
    }
    .branch-card.is-main .branch-card-header {
        background: linear-gradient(135deg, rgba(16,185,129,.12) 0%, rgba(5,150,105,.04) 100%);
        border-bottom-color: rgba(16,185,129,.2);
    }

    /* Monogram */
    .branch-monogram {
        width: 44px; height: 44px; flex-shrink: 0;
        border-radius: .75rem;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1rem; letter-spacing: -.02em;
        color: var(--bs-secondary-color);
        background: var(--bs-card-bg);
        border: 1px solid var(--bs-border-color);
    }
    .branch-card.is-main .branch-monogram {
        color: #fff;
        background: linear-gradient(135deg, #10b981, #047857);
        border-color: rgba(16,185,129,.5);
        box-shadow: 0 4px 12px -4px rgba(16,185,129,.5);
    }

    .branch-name { font-size: 1rem; font-weight: 700; letter-spacing: -.01em; margin: 0; line-height: 1.25; }
    .branch-slug { font-family: ui-monospace, monospace; font-size: .7rem; color: var(--bs-secondary-color); }

    /* Body */
    .branch-card-body { padding: 1rem 1.25rem; }
    .branch-meta { font-size: .82rem; color: var(--bs-secondary-color); }
    .branch-meta i { width: 1rem; opacity: .75; }

    /* Stats row */
    .branch-stats { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-top: 1rem; }
    .branch-stat {
        display: flex; flex-direction: column; gap: .15rem;
        padding: .65rem .85rem; border-radius: .65rem;
        background: var(--bs-body-bg-alt, rgba(148,163,184,.06));
        border: 1px solid var(--bs-border-color);
    }
    .branch-stat .n { font-weight: 800; font-size: 1.35rem; line-height: 1; font-variant-numeric: tabular-nums; color: var(--bs-heading-color); }
    .branch-stat .l { font-size: .62rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--bs-secondary-color); }

    /* Footer actions */
    .branch-card .card-footer { background: transparent; border-top: 1px solid var(--bs-border-color); padding: .65rem 1.25rem; }

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
                <i class="bi bi-lock-fill me-1"></i>Add Branch (limit reached)
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

                {{-- Header band --}}
                <div class="branch-card-header d-flex align-items-center gap-3">
                    <div class="branch-monogram flex-shrink-0">{{ $initials ?: '–' }}</div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <h6 class="branch-name text-truncate mb-0">{{ $branch->name }}</h6>
                            <x-badge :status="$branch->is_active ? 'active' : 'expired'" class="flex-shrink-0">
                                {{ $branch->is_active ? 'Active' : 'Inactive' }}
                            </x-badge>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="branch-slug">{{ $branch->slug }}</span>
                            @if($branch->is_main)
                                <span class="badge bg-success-subtle text-success rounded-pill" style="font-size:.6rem">Main</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Body --}}
                <div class="branch-card-body">
                    <div class="branch-meta d-flex flex-column gap-1">
                        <div class="d-flex gap-2">
                            <i class="bi bi-geo-alt mt-1"></i>
                            <span>
                                @if($branch->address || $branch->city)
                                    {{ $branch->address }}@if($branch->address && $branch->city), @endif{{ $branch->city }}
                                @else
                                    <span class="text-muted fst-italic">No address set</span>
                                @endif
                            </span>
                        </div>
                        @if($branch->phone)
                            <div class="d-flex gap-2"><i class="bi bi-telephone"></i><span>{{ $branch->phone }}</span></div>
                        @endif
                        @if($branch->email)
                            <div class="d-flex gap-2"><i class="bi bi-envelope"></i><span class="text-truncate">{{ $branch->email }}</span></div>
                        @endif
                    </div>

                    <div class="branch-stats">
                        <div class="branch-stat">
                            <span class="n">{{ $branch->courts_count }}</span>
                            <span class="l">Courts</span>
                        </div>
                        <div class="branch-stat">
                            <span class="n">{{ $branch->staff_count }}</span>
                            <span class="l">Staff</span>
                        </div>
                    </div>
                </div>

                {{-- Footer actions --}}
                <div class="card-footer d-flex align-items-center gap-2">
                    @can('update', $branch)
                    <a href="{{ route('admin.branches.edit', $branch) }}"
                       class="btn btn-primary flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    @endcan
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#branchQrModal"
                            data-branch-name="{{ $branch->name }}"
                            data-signup-url="{{ $signupUrl }}"
                            title="Customer signup QR">
                        <i class="bi bi-qr-code"></i>
                    </button>
                    @can('delete', $branch)
                    <form method="POST" action="{{ route('admin.branches.destroy', $branch) }}"
                          onsubmit="return confirm('Delete branch {{ $branch->name }}?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger" title="Delete branch">
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
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
        <div class="modal-content border-0 shadow-lg overflow-hidden">

            {{-- Header --}}
            <div class="modal-header border-0 pb-0">
                <div>
                    <h6 class="modal-title fw-bold mb-0" id="qrBranchName"></h6>
                    <p class="text-muted small mb-0">Customer signup QR</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- QR --}}
            <div class="modal-body text-center pt-3 pb-2">
                <div class="d-inline-flex align-items-center justify-content-center rounded-3 border p-3 mb-4"
                     style="background:#fff">
                    <img id="qrImage" src="" alt="Signup QR code"
                         style="width:220px;height:220px;display:block">
                </div>

                <p class="text-muted small mb-4 px-2">
                    Print and place at the front desk. New signups will be assigned to
                    <strong id="qrBranchNameInline" class="text-body"></strong>.
                </p>

                {{-- URL copy row --}}
                <div class="input-group mb-4">
                    <input type="text" id="qrUrl" class="form-control form-control-sm font-monospace text-muted" readonly>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" id="qrCopyBtn"
                            onclick="
                                const i = document.getElementById('qrUrl'); i.select(); document.execCommand('copy');
                                const b = document.getElementById('qrCopyBtn');
                                b.innerHTML = '<i class=\'bi bi-check2 me-1\'></i>Copied';
                                b.classList.replace('btn-outline-secondary','btn-outline-success');
                                setTimeout(() => { b.innerHTML = '<i class=\'bi bi-clipboard me-1\'></i>Copy'; b.classList.replace('btn-outline-success','btn-outline-secondary'); }, 1800);
                            ">
                        <i class="bi bi-clipboard me-1"></i>Copy
                    </button>
                </div>

                {{-- Actions --}}
                <div class="d-grid gap-2">
                    <a id="qrVisit" href="#" target="_blank" class="btn btn-primary">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Preview signup page
                    </a>
                    <a id="qrPrint" href="#" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-1"></i>Open / Print QR
                    </a>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0"></div>
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
