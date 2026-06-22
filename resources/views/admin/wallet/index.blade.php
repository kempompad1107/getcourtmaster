@extends('layouts.app')
@section('title', 'Wallet Management')

@push('styles')
<style>
    .wallet-hero {
        position: relative; overflow: hidden;
        border: 1px solid rgba(16,185,129,.28);
        background:
            radial-gradient(120% 140% at 100% 0%, rgba(16,185,129,.18) 0%, transparent 55%),
            linear-gradient(135deg, rgba(16,185,129,.12) 0%, rgba(5,150,105,.02) 45%),
            var(--bs-card-bg);
    }
    .wallet-hero-watermark {
        position: absolute; right: -.4rem; bottom: -1.8rem;
        font-size: 8rem; line-height: 1; color: rgba(16,185,129,.07);
        pointer-events: none;
    }
    .wallet-hero-label {
        font-size: .72rem; font-weight: 600; letter-spacing: .12em;
        text-transform: uppercase; color: var(--bs-secondary-color); margin: 0;
    }
    .wallet-hero-value {
        font-size: clamp(2rem, 4vw, 2.9rem); font-weight: 800;
        letter-spacing: -.02em; line-height: 1; margin: .35rem 0 0;
    }
    .wallet-metric {
        display: flex; align-items: center; gap: .8rem;
        padding: .85rem 1.1rem; border-radius: .9rem;
        background: var(--bs-body-bg-alt, rgba(148,163,184,.06));
        border: 1px solid var(--bs-border-color);
    }
    .wallet-metric-ico {
        width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1.15rem;
    }
    .wallet-metric-label { font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--bs-secondary-color); margin: 0; }
    .wallet-metric-value { font-size: 1.15rem; font-weight: 700; margin: .1rem 0 0; line-height: 1; white-space: nowrap; }

    .wallet-avatar {
        width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 700; font-size: .82rem;
        color: #fff; background: linear-gradient(135deg, #10b981, #059669);
    }
    .wallet-balance-pill {
        display: inline-block; padding: .28rem .7rem; border-radius: 999px;
        font-weight: 700; font-size: .85rem;
        background: rgba(16,185,129,.12); color: #10b981;
        border: 1px solid rgba(16,185,129,.22);
    }
    .wallet-balance-pill.is-zero {
        background: rgba(148,163,184,.1); color: var(--bs-secondary-color);
        border-color: var(--bs-border-color);
    }
    /* TailAdmin table header */
    .wallet-roster thead th {
        text-transform: uppercase; font-size: .7rem; letter-spacing: .04em;
        font-weight: 600; color: var(--bs-secondary-color);
        padding-top: .85rem; padding-bottom: .85rem;
    }
    .wallet-roster tbody td { padding-top: .75rem; padding-bottom: .75rem; }
    .wallet-roster tbody tr { transition: background-color .15s; }

    .wallet-feed-ico {
        width: 36px; height: 36px; border-radius: 11px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1rem;
    }
    .wallet-feed-amount { font-variant-numeric: tabular-nums; font-size: .95rem; }

    .section-label {
        font-size: .68rem; font-weight: 600; letter-spacing: .07em;
        text-transform: uppercase; color: var(--bs-secondary-color);
        display: block; margin-bottom: .75rem;
    }
</style>
@endpush

@section('content')

<x-page-header title="Wallet Management" subtitle="Manual top-ups, deductions and audit trail"/>

<div class="alert alert-info border-0 small d-flex gap-2 align-items-start mb-4">
    <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
    <div>
        Customers cannot top up their own wallets. All balance changes must be processed
        manually by you or your staff — every entry is logged with the processor's name,
        timestamp and an optional reference note.
    </div>
</div>

{{-- Balance hero --}}
<div class="card wallet-hero mb-4">
    <i class="bi bi-wallet2 wallet-hero-watermark"></i>
    <div class="card-body p-4">
        <div class="row align-items-center g-4">
            <div class="col-12 col-lg-5">
                <p class="wallet-hero-label">Customer balances held</p>
                <p class="wallet-hero-value">₱{{ number_format($stats['total_balance'], 2) }}</p>
                <p class="small text-muted mb-0 mt-2">
                    <i class="bi bi-people me-1"></i>Across {{ $customers->total() }} customer{{ $customers->total() === 1 ? '' : 's' }}
                </p>
            </div>
            <div class="col-12 col-sm-6 col-lg-3 offset-lg-1">
                <div class="wallet-metric">
                    <div class="wallet-metric-ico bg-success bg-opacity-10 text-success">
                        <i class="bi bi-arrow-down-left"></i>
                    </div>
                    <div>
                        <p class="wallet-metric-label">Credited today</p>
                        <p class="wallet-metric-value text-success">₱{{ number_format($stats['credited_today'], 2) }}</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="wallet-metric">
                    <div class="wallet-metric-ico bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-arrow-up-right"></i>
                    </div>
                    <div>
                        <p class="wallet-metric-label">Debited today</p>
                        <p class="wallet-metric-value text-warning">₱{{ number_format($stats['debited_today'], 2) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    {{-- Customer roster --}}
    <div class="col-12 col-lg-7">
        <div class="card">

            {{-- Search inside card body --}}
            <div class="card-body pb-0">
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                    <span class="section-label mb-0">Customers</span>
                    <form method="GET" class="d-flex gap-2 align-items-center flex-grow-1" style="max-width:320px;margin-left:auto">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" name="search" value="{{ $search }}"
                                   placeholder="Name, email or phone…"
                                   class="form-control">
                            @if($search !== '')
                            <a href="{{ route('admin.wallet.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i>
                            </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            @if($customers->isEmpty())
                <div class="card-body pt-0">
                    <x-empty-state title="No customers found" icon="bi-people"
                        description="Try a different search term."/>
                </div>
            @else
            <div class="table-responsive">
                <table class="table wallet-roster table-stack align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Customer</th>
                            <th class="text-end">Balance</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($customers as $c)
                        @php
                            $initials = collect(explode(' ', trim($c->name)))
                                ->filter()->take(2)
                                ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                                ->implode('') ?: '?';
                            $hasBalance = $c->wallet_balance > 0;
                        @endphp
                        <tr>
                            <td class="cell-plain">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="wallet-avatar">{{ $initials }}</span>
                                    <div class="min-w-0">
                                        <div class="fw-semibold small text-truncate">{{ $c->name }}</div>
                                        <div class="small text-muted text-truncate">{{ $c->email }}@if($c->phone) · {{ $c->phone }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Balance" class="text-end">
                                <span class="wallet-balance-pill {{ $hasBalance ? '' : 'is-zero' }}">
                                    ₱{{ number_format($c->wallet_balance, 2) }}
                                </span>
                            </td>
                            <td class="cell-actions text-end">
                                <a href="{{ route('admin.wallet.show', $c) }}" class="btn btn-primary btn-sm">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($customers->hasPages())
            <div class="px-4 py-3 border-top">{{ $customers->withQueryString()->links() }}</div>
            @endif
            @endif

        </div>
    </div>

    {{-- Recent activity --}}
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-body pb-0">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="section-label mb-0">Recent Activity</span>
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill">Last 50</span>
                </div>
            </div>

            @if($recent->isEmpty())
            <div class="card-body pt-0">
                <x-empty-state title="No wallet activity yet" icon="bi-clock-history"/>
            </div>
            @else
            <div class="list-group list-group-flush">
                @foreach($recent as $tx)
                @php
                    $isCredit = in_array($tx->type, ['credit', 'refund', 'reward']);
                    $colour   = $isCredit ? 'success' : 'danger';
                    $icon     = $isCredit ? 'bi-arrow-down-left' : 'bi-arrow-up-right';
                @endphp
                <div class="list-group-item py-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="wallet-feed-ico bg-{{ $colour }} bg-opacity-10 text-{{ $colour }}">
                            <i class="bi {{ $icon }}"></i>
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex justify-content-between gap-2">
                                <p class="mb-0 small fw-semibold text-truncate">
                                    <a href="{{ route('admin.wallet.show', $tx->user_id) }}" class="text-decoration-none text-reset">
                                        {{ $tx->user?->name ?? '—' }}
                                    </a>
                                </p>
                                <span class="wallet-feed-amount fw-bold text-{{ $colour }} flex-shrink-0">
                                    {{ $isCredit ? '+' : '−' }}₱{{ number_format($tx->amount, 2) }}
                                </span>
                            </div>
                            <p class="mb-1 small text-muted text-truncate">{{ $tx->description ?: ucfirst($tx->type) }}</p>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-{{ $colour }}-subtle text-{{ $colour }} text-capitalize rounded-pill">{{ $tx->type }}</span>
                                <small class="text-muted">{{ $tx->created_at->format('M j, g:i A') }}</small>
                                @if($tx->processedBy)
                                <small class="text-muted">· by {{ $tx->processedBy->name }}</small>
                                @endif
                            </div>
                            @if($tx->note)
                            <div class="small fst-italic text-muted mt-1">"{{ $tx->note }}"</div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

        </div>
    </div>

</div>

@endsection
