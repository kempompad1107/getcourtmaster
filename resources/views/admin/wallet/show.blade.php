@extends('layouts.app')
@section('title', 'Wallet — ' . $customer->name)

@section('content')

<x-page-header :title="$customer->name . ' — Wallet'" :back="route('admin.wallet.index')"
               subtitle="Manual top-up, deduction and ledger">
    <x-slot name="actions">
        <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-person me-1"></i>Customer profile
        </a>
    </x-slot>
</x-page-header>

{{-- Balance header --}}
<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="text-muted small">Current balance</div>
            <div class="display-6 fw-bold mb-0">₱{{ number_format($customer->wallet_balance, 2) }}</div>
            <div class="small text-muted mt-1">
                {{ $customer->email }}@if($customer->phone) · {{ $customer->phone }}@endif
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#creditWalletModal">
                <i class="bi bi-plus-circle me-1"></i>Add Balance
            </button>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#debitWalletModal"
                    @disabled($customer->wallet_balance <= 0)>
                <i class="bi bi-dash-circle me-1"></i>Deduct Balance
            </button>
        </div>
    </div>
</div>

{{-- Audit log --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Transaction history</h6></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>When</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Note</th>
                    <th>Processed by</th>
                    <th>Reference</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Balance after</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                    @php
                        $isCredit = in_array($tx->type, ['credit', 'refund', 'reward']);
                        $colour   = $isCredit ? 'success' : 'danger';
                    @endphp
                    <tr>
                        <td class="text-muted small text-nowrap">{{ $tx->created_at->format('M d, Y · g:i A') }}</td>
                        <td><span class="badge bg-{{ $colour }}-subtle text-{{ $colour }} text-capitalize">{{ $tx->type }}</span></td>
                        <td>{{ $tx->description ?: '—' }}</td>
                        <td class="small text-muted">{{ $tx->note ?: '—' }}</td>
                        <td class="small">{{ $tx->processedBy?->name ?? '—' }}</td>
                        <td><code class="small text-muted">{{ $tx->reference }}</code></td>
                        <td class="text-end fw-medium text-{{ $colour }}">
                            {{ $isCredit ? '+' : '−' }}₱{{ number_format($tx->amount, 2) }}
                        </td>
                        <td class="text-end">₱{{ number_format($tx->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
        <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>

@endsection

@push('modals')
{{-- Credit wallet --}}
<div class="modal fade" id="creditWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.customers.credit', $customer) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title">Add Balance — {{ $customer->name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <label class="form-label small fw-medium">Reference</label>
                    <input type="text" name="reference" maxlength="120" class="form-control"
                           placeholder="e.g. OR #5821, BPI deposit slip">
                    <div class="form-text small">Optional. Appears in the description for cross-checking.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Internal note</label>
                    <textarea name="note" maxlength="500" rows="2" class="form-control"
                              placeholder="Anything staff should remember about this top-up"></textarea>
                </div>
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-shield-check me-1"></i>
                    Logged as processed by <strong>{{ auth()->user()->name }}</strong> at {{ now()->format('M j, Y · g:i A') }}.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Credit Wallet</button>
            </div>
        </form>
    </div>
</div>

{{-- Debit wallet --}}
<div class="modal fade" id="debitWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.customers.debit', $customer) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title">Deduct Balance — {{ $customer->name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                <div class="mb-3">
                    <label class="form-label small fw-medium">Internal note</label>
                    <textarea name="note" maxlength="500" rows="2" class="form-control"
                              placeholder="Optional context for the audit trail"></textarea>
                </div>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Deductions are visible to the customer in their wallet history.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-dash-circle me-1"></i>Deduct Wallet</button>
            </div>
        </form>
    </div>
</div>
@endpush
