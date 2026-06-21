@extends('layouts.app')
@section('title', $customer->name)

@push('styles')
<style>
    .cu-profile-avatar {
        width: 64px; height: 64px; border-radius: 50%; flex-shrink: 0; object-fit: cover;
        display: grid; place-items: center; font-weight: 700; font-size: 1.4rem;
        color: #fff; background: linear-gradient(135deg, #10b981, #059669);
    }
    .cu-wallet-box {
        background: var(--bs-body-bg-alt, rgba(148,163,184,.06));
        border: 1px solid var(--bs-border-color);
        border-radius: .85rem; padding: 1rem 1.15rem;
    }
    .section-label {
        font-size: .68rem; font-weight: 600; letter-spacing: .07em;
        text-transform: uppercase; color: var(--bs-secondary-color);
        margin-bottom: .85rem; display: block;
    }
    .activity-row {
        display: flex; align-items: center; justify-content: space-between;
        gap: 1rem; padding: .65rem 0;
        border-bottom: 1px solid var(--bs-border-color);
    }
    .activity-row:last-child { border-bottom: 0; }
</style>
@endpush

@section('content')

<x-page-header :title="$customer->name" :back="route('admin.customers.index')" backLabel="Customers"
    subtitle="Customer profile">
    <x-slot name="actions">
        <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-outline-secondary">
            <i class="bi bi-pencil"></i>Edit
        </a>
    </x-slot>
</x-page-header>

{{-- Profile card --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-4 align-items-start">

            {{-- Avatar + info --}}
            <div class="col-12 col-sm-auto d-flex align-items-center gap-3">
                @if($customer->avatar)
                <img src="{{ $customer->avatar_url }}" class="cu-profile-avatar" alt="{{ $customer->name }}">
                @else
                <div class="cu-profile-avatar">{{ strtoupper(substr($customer->name, 0, 1)) }}</div>
                @endif
                <div class="min-w-0 d-sm-none">
                    <h6 class="fw-semibold mb-0">{{ $customer->name }}</h6>
                    <small class="text-muted">{{ $customer->email }}</small>
                </div>
            </div>

            {{-- Details --}}
            <div class="col-12 col-sm flex-grow-1 min-w-0">
                <h5 class="fw-semibold mb-1 d-none d-sm-block">{{ $customer->name }}</h5>
                <div class="d-flex flex-wrap gap-x-3 gap-2 small text-muted mb-2">
                    <span><i class="bi bi-envelope me-1"></i>{{ $customer->email }}</span>
                    @if($customer->phone)
                    <span><i class="bi bi-phone me-1"></i>{{ $customer->phone }}</span>
                    @endif
                    <span><i class="bi bi-calendar me-1"></i>Since {{ $customer->created_at->format('F Y') }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @if($customer->activeMembership)
                    <span class="badge badge-soft-purple">
                        {{ $customer->activeMembership->plan->name }}
                        &bull; expires {{ $customer->activeMembership->expires_at->format('M j, Y') }}
                    </span>
                    @endif
                    @if(!$customer->is_active)
                    <span class="badge bg-danger-subtle text-danger">Inactive</span>
                    @endif
                    @if($customer->homeBranch)
                    <span class="small text-muted">
                        <i class="bi bi-house-door text-success me-1"></i>{{ $customer->homeBranch->name }}
                    </span>
                    @endif
                </div>
                @if(isset($branchesVisited) && $branchesVisited->isNotEmpty())
                <div class="mt-2 d-flex flex-wrap align-items-center gap-1">
                    <small class="text-muted me-1"><i class="bi bi-shop me-1"></i>Visited:</small>
                    @foreach($branchesVisited as $b)
                    <span class="badge text-bg-light border">{{ $b->name }}@if($b->is_main) <span class="text-muted">(Main)</span>@endif</span>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Wallet --}}
            <div class="col-12 col-sm-auto">
                <div class="cu-wallet-box text-center" style="min-width:160px">
                    <p class="text-muted small mb-1">Wallet Balance</p>
                    <p class="fw-bold mb-3" style="font-size:1.6rem;line-height:1">₱{{ number_format($customer->wallet_balance, 2) }}</p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm flex-grow-1"
                                data-bs-toggle="modal" data-bs-target="#creditWalletModal">
                            <i class="bi bi-plus-circle"></i>Credit
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm flex-grow-1"
                                data-bs-toggle="modal" data-bs-target="#debitWalletModal"
                                @disabled($customer->wallet_balance <= 0)>
                            <i class="bi bi-dash-circle"></i>Debit
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-4">
        <x-stat-card label="Total Bookings"  :value="$stats['total_bookings']"                       icon="bi-calendar-check"  color="green"/>
    </div>
    <div class="col-6 col-sm-4">
        <x-stat-card label="Lifetime Spend"  :value="'₱'.number_format($stats['total_spent'],2)"     icon="bi-currency-dollar" color="emerald" :small="true"/>
    </div>
    <div class="col-12 col-sm-4">
        <x-stat-card label="Membership"      :value="$stats['membership_status']"                    icon="bi-credit-card"     color="amber"/>
    </div>
</div>

<div class="row g-4">

    {{-- Recent bookings --}}
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-body pb-0">
                <span class="section-label">Recent Bookings</span>
            </div>
            @if($bookings->isEmpty())
                <div class="card-body pt-0">
                    <x-empty-state title="No bookings yet" icon="bi-calendar"/>
                </div>
            @else
            <div class="list-group list-group-flush">
                @foreach($bookings as $booking)
                @php $sc = $booking->status === 'completed' ? 'text-muted' : ($booking->status === 'cancelled' ? 'text-danger' : 'text-success'); @endphp
                <a href="{{ route('admin.bookings.show', $booking) }}"
                   class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3">
                    <div class="min-w-0">
                        <p class="mb-0 small fw-medium">{{ $booking->court->name }}</p>
                        <small class="text-muted">
                            {{ $booking->booking_date->format('M j, Y') }} &bull;
                            {{ \Carbon\Carbon::parse($booking->start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($booking->end_time)->format('g:i A') }}
                        </small>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <p class="mb-0 small fw-semibold">₱{{ number_format($booking->total_amount, 2) }}</p>
                        <small class="{{ $sc }}">{{ ucfirst($booking->status) }}</small>
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-12 col-lg-4">

        {{-- Wallet transactions --}}
        <div class="card mb-4">
            <div class="card-body pb-0">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="section-label mb-0">Wallet Activity</span>
                    <small class="text-muted">Last 10</small>
                </div>
            </div>
            @if($walletTransactions->isEmpty())
            <div class="card-body pt-1">
                <p class="text-muted small text-center mb-0">No wallet activity yet.</p>
            </div>
            @else
            <div class="card-body pt-1">
                @foreach($walletTransactions as $tx)
                @php $isCredit = in_array($tx->type, ['credit', 'refund', 'reward']); $colour = $isCredit ? 'success' : 'danger'; @endphp
                <div class="activity-row">
                    <div class="me-2 min-w-0">
                        <p class="mb-0 small fw-medium text-truncate">{{ $tx->description ?: ucfirst($tx->type) }}</p>
                        <small class="text-muted">{{ $tx->created_at->format('M j, g:i A') }}</small>
                    </div>
                    <span class="small fw-semibold text-{{ $colour }} flex-shrink-0">
                        {{ $isCredit ? '+' : '−' }}₱{{ number_format($tx->amount, 2) }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Payments --}}
        <div class="card mb-4">
            <div class="card-body pb-0">
                <span class="section-label">Payments</span>
            </div>
            @if($payments->isEmpty())
            <div class="card-body pt-0">
                <p class="text-muted small text-center mb-0">No payments yet.</p>
            </div>
            @else
            <div class="list-group list-group-flush">
                @foreach($payments->take(5) as $payment)
                <div class="list-group-item d-flex align-items-center justify-content-between py-2">
                    <div>
                        <p class="mb-0 small fw-medium">{{ ucfirst($payment->method) }}</p>
                        <small class="text-muted">{{ $payment->created_at->format('M j') }}</small>
                    </div>
                    <span class="small fw-semibold {{ $payment->status === 'paid' ? 'text-success' : 'text-danger' }}">
                        ₱{{ number_format($payment->amount, 2) }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Staff notes --}}
        <div class="card">
            <div class="card-body pb-0">
                <span class="section-label">Staff Notes</span>
            </div>
            @if($notes->isEmpty())
            <div class="card-body pt-0 pb-2">
                <p class="text-muted small text-center mb-0">No notes yet.</p>
            </div>
            @else
            <div class="list-group list-group-flush">
                @foreach($notes as $note)
                <div class="list-group-item py-2">
                    <p class="mb-0 small">{{ $note->note }}</p>
                    <small class="text-muted">{{ $note->createdBy->name ?? 'Staff' }} &bull; {{ $note->created_at->diffForHumans() }}</small>
                </div>
                @endforeach
            </div>
            @endif
            <div class="card-footer">
                <form method="POST" action="{{ route('admin.customers.note', $customer) }}">
                    @csrf
                    <textarea name="note" rows="2" placeholder="Add a note…"
                              class="form-control mb-2"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Save Note</button>
                </form>
            </div>
        </div>

    </div>
</div>

@endsection

@push('modals')
{{-- Credit wallet modal --}}
<x-modal name="creditWalletModal" title="Credit Wallet" subtitle="{{ $customer->name }}">
    <form method="POST" action="{{ route('admin.customers.credit', $customer) }}" id="credit-wallet-form">
        @csrf
        <p class="small text-muted mb-3">
            Current balance: <strong>₱{{ number_format($customer->wallet_balance, 2) }}</strong>
        </p>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Amount (₱) <span class="text-danger">*</span></label>
                <input type="number" name="amount" min="1" max="100000" step="0.01" required
                       class="form-control" placeholder="500.00">
            </div>
            <div class="col-12">
                <label class="form-label">Note <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" name="note" maxlength="255" class="form-control"
                       placeholder="e.g. Cash deposit, BPI transfer #12345">
            </div>
        </div>
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="credit-wallet-form" class="btn btn-success">
            <i class="bi bi-plus-circle"></i>Credit Wallet
        </button>
    </x-slot>
</x-modal>

{{-- Debit wallet modal --}}
<x-modal name="debitWalletModal" title="Debit Wallet" subtitle="{{ $customer->name }}">
    <form method="POST" action="{{ route('admin.customers.debit', $customer) }}" id="debit-wallet-form">
        @csrf
        <p class="small text-muted mb-3">
            Available balance: <strong>₱{{ number_format($customer->wallet_balance, 2) }}</strong>
        </p>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Amount (₱) <span class="text-danger">*</span></label>
                <input type="number" name="amount" min="1" max="{{ $customer->wallet_balance }}" step="0.01" required
                       class="form-control" placeholder="100.00">
            </div>
            <div class="col-12">
                <label class="form-label">Reason <span class="text-danger">*</span></label>
                <input type="text" name="reason" maxlength="255" required class="form-control"
                       placeholder="e.g. Refund cancellation, adjust over-credit">
            </div>
        </div>
        <div class="alert alert-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Debits are logged in the wallet ledger and visible to the customer.
        </div>
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="debit-wallet-form" class="btn btn-danger">
            <i class="bi bi-dash-circle"></i>Debit Wallet
        </button>
    </x-slot>
</x-modal>
@endpush
