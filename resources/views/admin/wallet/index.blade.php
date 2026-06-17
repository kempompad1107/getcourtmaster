@extends('layouts.app')
@section('title', 'Wallet Management')

@push('styles')
<style>
    /* ── Wallet management — scoped polish over the admin theme ── */
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
        width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 700; font-size: .85rem;
        color: #fff; background: linear-gradient(135deg, #10b981, #059669);
        box-shadow: 0 4px 12px -4px rgba(16,185,129,.6);
    }
    .wallet-balance-pill {
        display: inline-block; padding: .28rem .7rem; border-radius: 999px;
        font-weight: 700; font-size: .9rem;
        background: rgba(16,185,129,.12); color: #34d399;
        border: 1px solid rgba(16,185,129,.22);
    }
    .wallet-balance-pill.is-zero { background: rgba(148,163,184,.1); color: var(--bs-secondary-color); border-color: var(--bs-border-color); }

    .wallet-roster tbody tr { transition: background-color .15s; }
    .wallet-roster tbody tr:hover { background: rgba(16,185,129,.05); }

    .wallet-feed-item { transition: background-color .15s; }
    .wallet-feed-item:hover { background: rgba(148,163,184,.05); }
    .wallet-feed-ico {
        width: 36px; height: 36px; border-radius: 11px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1rem;
    }
    .wallet-feed-amount { font-variant-numeric: tabular-nums; font-size: .95rem; }
</style>
@endpush

@section('content')

<x-page-header title="Wallet Management" subtitle="Manual top-ups, deductions and audit trail">
    <x-slot name="actions">
        <span class="badge bg-info-subtle text-info rounded-pill px-3 py-2">
            <i class="bi bi-shield-lock me-1"></i>Owner / Staff only
        </span>
    </x-slot>
</x-page-header>

<div class="alert alert-warning border-0 small d-flex gap-2 align-items-start mb-4">
    <i class="bi bi-info-circle-fill mt-1"></i>
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
        <div class="card h-100">
            <div class="card-header">
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <h6 class="mb-0 fw-semibold flex-grow-1">
                        <i class="bi bi-people me-1 text-muted"></i>Customers
                    </h6>
                    <div class="input-group input-group-sm" style="max-width:320px">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" value="{{ $search }}"
                               placeholder="Search name, email or phone…"
                               class="form-control form-control-sm">
                        @if($search !== '')
                            <a href="{{ route('admin.wallet.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table wallet-roster align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Customer</th>
                            <th class="text-end">Balance</th>
                            <th style="width:1%"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($customers as $c)
                        @php
                            $initials = collect(explode(' ', trim($c->name)))
                                ->filter()->take(2)
                                ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                                ->implode('') ?: '?';
                            $hasBalance = $c->wallet_balance > 0;
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="wallet-avatar">{{ $initials }}</span>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate">{{ $c->name }}</div>
                                        <div class="small text-muted text-truncate">{{ $c->email }}@if($c->phone) · {{ $c->phone }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end">
                                <span class="wallet-balance-pill {{ $hasBalance ? '' : 'is-zero' }}">₱{{ number_format($c->wallet_balance, 2) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.wallet.show', $c) }}" class="btn btn-sm btn-outline-primary text-nowrap">
                                    Manage<i class="bi bi-chevron-right ms-1 small"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-5">
                            <i class="bi bi-people fs-3 d-block mb-2 opacity-50"></i>No customers found.
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($customers->hasPages())
                <div class="card-footer">{{ $customers->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Recent activity --}}
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-clock-history me-1 text-muted"></i>Recent wallet activity
                </h6>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill">Last 50</span>
            </div>
            <div class="list-group list-group-flush" style="max-height:560px;overflow-y:auto">
                @forelse($recent as $tx)
                    @php
                        $isCredit = in_array($tx->type, ['credit', 'refund', 'reward']);
                        $colour   = $isCredit ? 'success' : 'danger';
                        $icon     = $isCredit ? 'bi-arrow-down-left' : 'bi-arrow-up-right';
                    @endphp
                    <div class="list-group-item wallet-feed-item py-3">
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
                                    <div class="small fst-italic text-muted mt-1">“{{ $tx->note }}”</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-center text-muted py-5">
                        <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>No wallet activity yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection
