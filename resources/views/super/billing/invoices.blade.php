@extends('layouts.super')
@section('title', 'Subscription Invoices')

@push('styles')
@include('super._partials.premium-ui')
@endpush

@section('content')

<x-page-header title="Subscription Invoices" subtitle="All tenants — billing & collections"/>

{{-- KPIs --}}
<div class="kpi-grid mb-4" style="--kpi-cols:3">
    <x-stat-card label="Outstanding" :value="'₱'.number_format($totals['pending'], 2)" icon="bi-hourglass-split" color="amber"/>
    <x-stat-card label="Collected" :value="'₱'.number_format($totals['paid'], 2)" icon="bi-check-circle" color="emerald"/>
    <x-stat-card label="Overdue" :value="$totals['overdue']" icon="bi-exclamation-triangle" color="red"/>
</div>

{{-- Filters --}}
<form method="GET" class="card mb-3">
    <div class="card-body py-3 d-flex flex-wrap align-items-end gap-2">
        <div>
            <label class="form-label small mb-0">Status</label>
            <select name="status" class="form-select form-select-sm" style="width:auto">
                <option value="">All</option>
                @foreach(['pending','paid','failed','refunded','draft'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-0">Tenant</label>
            <select name="tenant_id" class="form-select form-select-sm" style="width:auto">
                <option value="">All tenants</option>
                @foreach($tenants as $t)
                    <option value="{{ $t->id }}" @selected((int) request('tenant_id') === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary btn-sm">Apply</button>
        @if(request()->hasAny(['status','tenant_id']))
            <a href="{{ route('super.billing.invoices') }}" class="btn btn-link btn-sm">Reset</a>
        @endif
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 pro-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Tenant</th>
                    <th>Plan</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th>Due</th>
                    <th>Paid</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td><code class="small">{{ $inv->invoice_number }}</code></td>
                    <td data-label="Tenant">
                        <a href="{{ route('super.tenants.show', $inv->tenant) }}" class="text-decoration-none fw-medium">{{ $inv->tenant?->name ?? '—' }}</a>
                    </td>
                    <td class="small text-muted tcell-hide" data-label="Plan">{{ $inv->subscription?->plan?->name ?? '—' }}</td>
                    <td class="text-end fw-medium" data-label="Total">₱{{ number_format($inv->total, 2) }}</td>
                    <td data-label="Status">
                        <x-badge :status="match($inv->status) { 'paid' => 'active', 'pending' => 'pending', 'failed' => 'cancelled', 'refunded' => 'cancelled', 'draft' => 'pending', default => 'neutral' }">{{ ucfirst($inv->status) }}</x-badge>
                        @if($inv->isOverdue())
                            <x-badge status="cancelled">Overdue</x-badge>
                        @endif
                    </td>
                    <td class="small text-muted tcell-hide" data-label="Due">{{ $inv->due_at?->format('M j, Y') ?? '—' }}</td>
                    <td class="small text-muted tcell-hide" data-label="Paid">{{ $inv->paid_at?->format('M j, Y') ?? '—' }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a href="{{ route('admin.subscription-invoices.pdf', $inv) }}" class="btn btn-sm btn-link p-1" title="PDF">
                                <i class="bi bi-file-pdf"></i>
                            </a>
                            @if($inv->status !== 'paid')
                                <button type="button" class="btn btn-sm btn-link p-1 text-success" data-bs-toggle="modal" data-bs-target="#payModal-{{ $inv->id }}" title="Mark paid">
                                    <i class="bi bi-cash-coin"></i>
                                </button>
                                <form method="POST" action="{{ route('super.billing.invoices.retry', $inv) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-link p-1 text-primary" title="Retry charge">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-5 small">No invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())
    <div class="card-footer d-flex justify-content-end">{{ $invoices->links() }}</div>
    @endif
</div>

@endsection

@push('modals')
@foreach($invoices as $inv)
    @if($inv->status !== 'paid')
    <div class="modal fade" id="payModal-{{ $inv->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('super.billing.invoices.mark-paid', $inv) }}" class="modal-content text-start">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Mark Invoice {{ $inv->invoice_number }} as Paid</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Tenant: <strong>{{ $inv->tenant?->name }}</strong><br>
                        Amount: <strong>₱{{ number_format($inv->total, 2) }}</strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label small">Payment method</label>
                        <select name="payment_gateway" class="form-select form-select-sm">
                            <option value="manual">Manual (cash / bank deposit)</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="stripe">Stripe</option>
                            <option value="paymongo">PayMongo</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Reference / Transaction #</label>
                        <input name="payment_reference" type="text" class="form-control form-control-sm" placeholder="e.g. BPI deposit slip 12345">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Paid at</label>
                        <input name="paid_at" type="datetime-local" class="form-control form-control-sm" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Marking paid also extends the subscription's <code>renews_at</code> and lifts any suspension on the tenant.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Mark Paid</button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endforeach
@endpush
