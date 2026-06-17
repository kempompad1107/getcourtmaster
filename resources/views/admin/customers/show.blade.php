@extends('layouts.app')
@section('title', $customer->name)

@section('content')

<x-page-header :title="$customer->name" :back="route('admin.customers.index')" subtitle="Customer profile">
    <x-slot name="actions">
        <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    </x-slot>
</x-page-header>

{{-- Profile card --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-start gap-4 flex-wrap">
            @if($customer->avatar)
            <img src="{{ $customer->avatar_url }}" class="rounded-circle flex-shrink-0"
                 style="width:64px;height:64px;object-fit:cover">
            @else
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold fs-4"
                 style="width:64px;height:64px;background:rgba(99,102,241,.12);color:#6366f1">
                {{ strtoupper(substr($customer->name, 0, 1)) }}
            </div>
            @endif
            <div class="flex-grow-1 min-w-0">
                <h5 class="fw-semibold mb-1">{{ $customer->name }}</h5>
                <div class="d-flex flex-wrap gap-2 small text-muted mb-2">
                    <span><i class="bi bi-envelope me-1"></i>{{ $customer->email }}</span>
                    @if($customer->phone)
                    <span><i class="bi bi-phone me-1"></i>{{ $customer->phone }}</span>
                    @endif
                    <span><i class="bi bi-calendar me-1"></i>Member since {{ $customer->created_at->format('F Y') }}</span>
                </div>
                @if($customer->activeMembership)
                <span class="badge badge-soft-purple">
                    {{ $customer->activeMembership->plan->name }}
                    &bull; expires {{ $customer->activeMembership->expires_at->format('M j, Y') }}
                </span>
                @endif

                @if($customer->homeBranch)
                <div class="mt-2 small">
                    <i class="bi bi-house-door text-success me-1"></i>
                    Home branch:
                    <strong>{{ $customer->homeBranch->name }}</strong>
                </div>
                @endif

                @if(isset($branchesVisited) && $branchesVisited->isNotEmpty())
                <div class="mt-2 d-flex flex-wrap align-items-center gap-1">
                    <small class="text-muted me-1">
                        <i class="bi bi-shop me-1"></i>Branches visited:
                    </small>
                    @foreach($branchesVisited as $b)
                        <span class="badge text-bg-light border">
                            {{ $b->name }}@if($b->is_main) <span class="text-muted">(Main)</span>@endif
                        </span>
                    @endforeach
                </div>
                @endif
            </div>
            <div class="text-end flex-shrink-0">
                <p class="text-muted small mb-1">Wallet balance</p>
                <p class="fw-bold fs-4 mb-2">₱{{ number_format($customer->wallet_balance, 2) }}</p>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#creditWalletModal">
                        <i class="bi bi-plus-circle me-1"></i>Credit
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#debitWalletModal"
                            @disabled($customer->wallet_balance <= 0)>
                        <i class="bi bi-dash-circle me-1"></i>Debit
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-3">
        <x-stat-card label="Total Bookings"   :value="$stats['total_bookings']"    icon="bi-calendar-check" color="green"/>
    </div>
    <div class="col-6 col-sm-3">
        <x-stat-card label="Lifetime Spend"   :value="'₱'.number_format($stats['total_spent'],2)" icon="bi-currency-dollar" color="emerald" :small="true"/>
    </div>
    <div class="col-6 col-sm-3">
        <x-stat-card label="Wallet Balance"   :value="'₱'.number_format($stats['wallet_balance'],2)" icon="bi-wallet2" color="purple" :small="true"/>
    </div>
    <div class="col-6 col-sm-3">
        <x-stat-card label="Membership"       :value="$stats['membership_status']" icon="bi-credit-card" color="amber"/>
    </div>
</div>

<div class="row g-4">
    {{-- Recent bookings --}}
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Recent Bookings</h6></div>
            <div class="list-group list-group-flush">
                @forelse($bookings as $booking)
                @php $sc = $booking->status === 'completed' ? 'text-muted' : ($booking->status === 'cancelled' ? 'text-danger' : 'text-success'); @endphp
                <div class="list-group-item d-flex align-items-center justify-content-between py-3">
                    <div>
                        <p class="mb-0 small fw-medium">{{ $booking->court->name }}</p>
                        <small class="text-muted">
                            {{ $booking->booking_date->format('M j, Y') }} &bull;
                            {{ \Carbon\Carbon::parse($booking->start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($booking->end_time)->format('g:i A') }}
                        </small>
                    </div>
                    <div class="text-end">
                        <p class="mb-0 small fw-semibold">₱{{ number_format($booking->total_amount, 2) }}</p>
                        <small class="{{ $sc }}">{{ ucfirst($booking->status) }}</small>
                    </div>
                </div>
                @empty
                <div class="list-group-item text-center text-muted small py-4">No bookings yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-12 col-lg-4">

        {{-- Wallet transactions --}}
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Recent Wallet Activity</h6>
                <span class="small text-muted">Last 10</span>
            </div>
            <div class="list-group list-group-flush">
                @forelse($walletTransactions as $tx)
                @php
                    $isCredit = in_array($tx->type, ['credit', 'refund', 'reward']);
                    $colour   = $isCredit ? 'success' : 'danger';
                @endphp
                <div class="list-group-item d-flex align-items-start justify-content-between py-2">
                    <div class="me-2 min-w-0">
                        <p class="mb-0 small fw-medium text-truncate">{{ $tx->description ?: ucfirst($tx->type) }}</p>
                        <small class="text-muted">
                            <span class="badge bg-{{ $colour }}-subtle text-{{ $colour }} text-capitalize">{{ $tx->type }}</span>
                            &middot; {{ $tx->created_at->format('M j, g:i A') }}
                        </small>
                    </div>
                    <span class="small fw-semibold text-{{ $colour }} flex-shrink-0">
                        {{ $isCredit ? '+' : '−' }}₱{{ number_format($tx->amount, 2) }}
                    </span>
                </div>
                @empty
                <div class="list-group-item text-center text-muted small py-3">No wallet activity yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Payments --}}
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Payments</h6></div>
            <div class="list-group list-group-flush">
                @forelse($payments->take(5) as $payment)
                <div class="list-group-item d-flex align-items-center justify-content-between py-2">
                    <div>
                        <p class="mb-0 small fw-medium">{{ ucfirst($payment->method) }}</p>
                        <small class="text-muted">{{ $payment->created_at->format('M j') }}</small>
                    </div>
                    <span class="small fw-medium {{ $payment->status === 'paid' ? 'text-success' : 'text-danger' }}">
                        ₱{{ number_format($payment->amount, 2) }}
                    </span>
                </div>
                @empty
                <div class="list-group-item text-center text-muted small py-3">No payments.</div>
                @endforelse
            </div>
        </div>

        {{-- Staff notes --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Staff Notes</h6></div>
            <div class="list-group list-group-flush">
                @forelse($notes as $note)
                <div class="list-group-item py-2">
                    <p class="mb-0 small">{{ $note->note }}</p>
                    <small class="text-muted">
                        {{ $note->createdBy->name ?? 'Staff' }} &bull; {{ $note->created_at->diffForHumans() }}
                    </small>
                </div>
                @empty
                <div class="list-group-item text-center text-muted small py-3">No notes.</div>
                @endforelse
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('admin.customers.note', $customer) }}">
                    @csrf
                    <textarea name="note" rows="2" placeholder="Add a note..."
                              class="form-control form-control-sm mb-2"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Save Note</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('modals')
{{-- Credit wallet modal --}}
<div class="modal fade" id="creditWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.customers.credit', $customer) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title">Credit Wallet — {{ $customer->name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Current balance: <strong>₱{{ number_format($customer->wallet_balance, 2) }}</strong>
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Amount (₱) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" min="1" max="100000" step="0.01" required
                           class="form-control" placeholder="500.00">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Note <span class="text-muted">(optional)</span></label>
                    <input type="text" name="note" maxlength="255" class="form-control"
                           placeholder="e.g. Cash deposit at front desk, BPI transfer #12345">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Credit Wallet</button>
            </div>
        </form>
    </div>
</div>

{{-- Debit wallet modal --}}
<div class="modal fade" id="debitWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.customers.debit', $customer) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title">Debit Wallet — {{ $customer->name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Available balance: <strong>₱{{ number_format($customer->wallet_balance, 2) }}</strong>
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Amount (₱) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" min="1" max="{{ $customer->wallet_balance }}" step="0.01" required
                           class="form-control" placeholder="100.00">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Reason <span class="text-danger">*</span></label>
                    <input type="text" name="reason" maxlength="255" required class="form-control"
                           placeholder="e.g. Refund cancellation, adjust over-credit">
                </div>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Debits are logged in the wallet ledger and visible to the customer. Use this for refunds, adjustments, or correcting an over-credit.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-dash-circle me-1"></i>Debit Wallet</button>
            </div>
        </form>
    </div>
</div>
@endpush
