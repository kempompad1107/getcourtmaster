@extends('layouts.app')
@section('title', 'Wallet — ' . $customer->name)

@push('styles')
<style>
    .wallet-show-hero {
        position: relative; overflow: hidden;
        border: 1px solid rgba(16,185,129,.28);
        background:
            radial-gradient(120% 140% at 100% 0%, rgba(16,185,129,.18) 0%, transparent 55%),
            linear-gradient(135deg, rgba(16,185,129,.12) 0%, rgba(5,150,105,.02) 45%),
            var(--bs-card-bg);
    }
    .wallet-show-hero-watermark {
        position: absolute; right: -.4rem; bottom: -1.8rem;
        font-size: 7rem; line-height: 1; color: rgba(16,185,129,.07); pointer-events: none;
    }
    .wallet-show-avatar {
        width: 52px; height: 52px; border-radius: 50%; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 700; font-size: 1.1rem;
        color: #fff; background: linear-gradient(135deg, #10b981, #059669);
    }
    /* TailAdmin table header */
    .wallet-tx-table thead th {
        text-transform: uppercase; font-size: .7rem; letter-spacing: .04em;
        font-weight: 600; color: var(--bs-secondary-color);
        padding-top: .85rem; padding-bottom: .85rem;
    }
    .wallet-tx-table tbody td { padding-top: .75rem; padding-bottom: .75rem; }
    .wallet-tx-table tbody tr { transition: background-color .15s; }
    .section-label {
        font-size: .68rem; font-weight: 600; letter-spacing: .07em;
        text-transform: uppercase; color: var(--bs-secondary-color); display: block;
    }
</style>
@endpush

@section('content')

<x-page-header :title="$customer->name . ' — Wallet'" :back="route('admin.wallet.index')" backLabel="Wallets"/>

{{-- Balance hero --}}
<div class="card wallet-show-hero mb-4">
    <i class="bi bi-wallet2 wallet-show-hero-watermark"></i>
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">

            {{-- Customer + balance --}}
            <div class="d-flex align-items-center gap-3">
                <div class="wallet-show-avatar">
                    {{ strtoupper(substr($customer->name, 0, 1)) }}
                </div>
                <div>
                    <p class="mb-0 fw-semibold">{{ $customer->name }}</p>
                    <p class="mb-2 small text-muted">
                        {{ $customer->email }}@if($customer->phone) · {{ $customer->phone }}@endif
                    </p>
                    <div>
                        <p class="mb-0" style="font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--bs-secondary-color)">Current Balance</p>
                        <p class="mb-0 fw-bold" style="font-size:clamp(1.8rem,4vw,2.5rem);letter-spacing:-.02em;line-height:1">
                            ₱{{ number_format($customer->wallet_balance, 2) }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-success"
                        data-bs-toggle="modal" data-bs-target="#creditWalletModal">
                    <i class="bi bi-plus-circle"></i>Credit
                </button>
                <button type="button" class="btn btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#debitWalletModal"
                        @disabled($customer->wallet_balance <= 0)>
                    <i class="bi bi-dash-circle"></i>Debit
                </button>
            </div>

        </div>
    </div>
</div>

{{-- Transaction history --}}
<div class="card">
    <div class="card-body pb-2">
        <span class="section-label">Transaction History</span>
    </div>

    @if($transactions->isEmpty())
        <div class="card-body pt-0">
            <x-empty-state title="No transactions yet" icon="bi-clock-history"
                description="Credits and debits will appear here."/>
        </div>
    @else
    <div class="table-responsive">
        <table class="table wallet-tx-table table-stack align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>When</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th class="d-none d-md-table-cell">Note</th>
                    <th class="d-none d-md-table-cell">Processed by</th>
                    <th class="d-none d-lg-table-cell">Reference</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end d-none d-md-table-cell">Balance after</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $tx)
                @php
                    $isCredit = in_array($tx->type, ['credit', 'refund', 'reward']);
                    $colour   = $isCredit ? 'success' : 'danger';
                @endphp
                <tr>
                    <td data-label="When" class="text-muted small text-nowrap">{{ $tx->created_at->format('M d, Y · g:i A') }}</td>
                    <td data-label="Type">
                        <span class="badge bg-{{ $colour }}-subtle text-{{ $colour }} text-capitalize">{{ $tx->type }}</span>
                    </td>
                    <td data-label="Description" class="small">{{ $tx->description ?: '—' }}</td>
                    <td data-label="Note" class="small text-muted d-none d-md-table-cell">{{ $tx->note ?: '—' }}</td>
                    <td data-label="By" class="small d-none d-md-table-cell">{{ $tx->processedBy?->name ?? '—' }}</td>
                    <td data-label="Reference" class="d-none d-lg-table-cell">
                        <code class="small text-muted">{{ $tx->reference }}</code>
                    </td>
                    <td data-label="Amount" class="text-end fw-semibold text-{{ $colour }} text-nowrap">
                        {{ $isCredit ? '+' : '−' }}₱{{ number_format($tx->amount, 2) }}
                    </td>
                    <td data-label="Balance after" class="text-end small text-nowrap d-none d-md-table-cell">
                        ₱{{ number_format($tx->balance_after, 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="px-4 py-3 border-top">{{ $transactions->withQueryString()->links() }}</div>
    @endif
    @endif
</div>

@endsection

@push('modals')
{{-- Credit wallet --}}
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
                <label class="form-label">Reference <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" name="reference" maxlength="120" class="form-control"
                       placeholder="e.g. OR #5821, BPI deposit slip">
                <div class="form-text">Appears in the transaction description for cross-checking.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Internal note <span class="text-muted fw-normal">(optional)</span></label>
                <textarea name="note" maxlength="500" rows="2" class="form-control"
                          placeholder="Anything staff should remember about this top-up"></textarea>
            </div>
        </div>
        <div class="alert alert-info small mt-3 mb-0">
            <i class="bi bi-shield-check me-1"></i>
            Logged as processed by <strong>{{ auth()->user()->name }}</strong> at {{ now()->format('M j, Y · g:i A') }}.
        </div>
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="credit-wallet-form" class="btn btn-success">
            <i class="bi bi-plus-circle"></i>Credit Wallet
        </button>
    </x-slot>
</x-modal>

{{-- Debit wallet --}}
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
            <div class="col-12">
                <label class="form-label">Internal note <span class="text-muted fw-normal">(optional)</span></label>
                <textarea name="note" maxlength="500" rows="2" class="form-control"
                          placeholder="Optional context for the audit trail"></textarea>
            </div>
        </div>
        <div class="alert alert-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Deductions are visible to the customer in their wallet history.
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
