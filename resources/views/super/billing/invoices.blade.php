@extends('layouts.super')
@section('title', 'Subscription Invoices')

@section('content')

<x-page-header title="Subscription Invoices" subtitle="All tenants — billing &amp; collections"/>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- KPIs --}}
<div class="kpi-grid mb-4" style="--kpi-cols:3">
    <x-stat-card label="Outstanding" :value="'₱'.number_format($totals['pending'], 2)" icon="bi-hourglass-split" color="amber"/>
    <x-stat-card label="Collected"   :value="'₱'.number_format($totals['paid'], 2)"    icon="bi-check-circle"   color="emerald"/>
    <x-stat-card label="Overdue"     :value="$totals['overdue']"                        icon="bi-exclamation-triangle" color="red"/>
</div>

{{-- Invoices table --}}
<div class="card">

    {{-- Filter bar in card header --}}
    <div class="card-header py-3">
        <form method="GET" action="{{ route('super.billing.invoices') }}"
              class="d-flex align-items-center gap-2 flex-wrap">

            <select name="status" class="form-select flex-shrink-0" style="width:140px;font-size:.875rem;"
                    onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @foreach(['pending','paid','failed','refunded','cancelled','draft'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>

            <select name="tenant_id" class="form-select flex-shrink-1" style="min-width:120px;max-width:200px;font-size:.875rem;"
                    onchange="this.form.submit()">
                <option value="">All Tenants</option>
                @foreach($tenants as $t)
                    <option value="{{ $t->id }}" @selected((int) request('tenant_id') === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="btn btn-primary flex-shrink-0" style="font-size:.875rem;">Filter</button>

            @if(request()->hasAny(['status','tenant_id']))
                <a href="{{ route('super.billing.invoices') }}" class="btn btn-outline-secondary flex-shrink-0" style="font-size:.875rem;">Clear</a>
            @endif

            <span class="small text-muted ms-auto flex-shrink-0 d-none d-sm-inline">
                {{ number_format($invoices->total()) }} invoice{{ $invoices->total() !== 1 ? 's' : '' }}
            </span>
        </form>
    </div>

    @if($invoices->isEmpty())
        <x-empty-state title="No invoices found" icon="bi-receipt"
            description="{{ request()->hasAny(['status','tenant_id']) ? 'No invoices match your current filters.' : 'Invoices will appear here once tenants are billed.' }}"/>
    @else
    <table class="table table-hover mb-0 align-middle table-stack">
        <thead class="table-light">
            <tr>
                <th>Invoice</th>
                <th class="d-none d-md-table-cell">Plan</th>
                <th>Amount</th>
                <th>Status</th>
                <th class="d-none d-lg-table-cell">Due</th>
                <th class="d-none d-lg-table-cell">Paid</th>
                <th class="cell-actions"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $inv)
            <tr>
                <td data-label="Invoice" class="cell-plain">
                    <div class="fw-semibold small">
                        <a href="{{ route('super.tenants.show', $inv->tenant) }}" class="text-decoration-none">
                            {{ $inv->tenant?->name ?? '—' }}
                        </a>
                    </div>
                    <code class="small text-muted">{{ $inv->invoice_number }}</code>
                    {{-- Mobile: show plan + dates inline --}}
                    <div class="d-md-none small text-muted mt-1">
                        {{ $inv->subscription?->plan?->name ?? '—' }}
                        @if($inv->due_at)
                            · Due {{ $inv->due_at->format('M j, Y') }}
                        @endif
                    </div>
                    @if($inv->payment_reference)
                        <div class="small text-muted d-none d-md-block mt-1">
                            <code class="small">{{ $inv->payment_reference }}</code>
                            @if($inv->payment_gateway)
                                <span class="opacity-60 ms-1">({{ $inv->payment_gateway }})</span>
                            @endif
                        </div>
                    @elseif($inv->failed_attempts > 0)
                        <div class="small text-danger d-none d-md-block mt-1">
                            <i class="bi bi-x-circle me-1"></i>{{ $inv->failed_attempts }} failed attempt{{ $inv->failed_attempts > 1 ? 's' : '' }}
                            @if($inv->next_retry_at)
                                <span class="text-muted ms-1">· Retry {{ $inv->next_retry_at->format('M j, H:i') }}</span>
                            @endif
                        </div>
                    @endif
                </td>
                <td data-label="Plan" class="small text-muted d-none d-md-table-cell">
                    {{ $inv->subscription?->plan?->name ?? '—' }}
                </td>
                <td data-label="Amount" class="fw-semibold text-nowrap">
                    ₱{{ number_format($inv->total, 2) }}
                </td>
                <td data-label="Status">
                    <x-badge :status="match($inv->status) {
                        'paid'      => 'active',
                        'pending'   => 'pending',
                        'draft'     => 'pending',
                        'failed'    => 'cancelled',
                        'refunded'  => 'cancelled',
                        default     => 'neutral'
                    }">{{ ucfirst($inv->status) }}</x-badge>
                    @if($inv->isOverdue())
                        <x-badge status="cancelled">Overdue</x-badge>
                    @endif
                </td>
                <td data-label="Due" class="small text-muted text-nowrap d-none d-lg-table-cell">
                    {{ $inv->due_at?->format('M j, Y') ?? '—' }}
                </td>
                <td data-label="Paid" class="small text-muted text-nowrap d-none d-lg-table-cell">
                    {{ $inv->paid_at?->format('M j, Y') ?? '—' }}
                </td>
                <td class="cell-actions">
                    <div class="d-flex align-items-center gap-1 justify-content-end flex-nowrap">
                        <a href="{{ route('admin.subscription-invoices.pdf', $inv) }}"
                           class="btn btn-outline-secondary btn-sm" title="Download PDF">
                            <i class="bi bi-file-pdf"></i>
                        </a>
                        @if($inv->status !== 'paid')
                        <div class="dropdown">
                            <button class="btn btn-primary btn-sm"
                                    type="button" data-bs-toggle="dropdown" data-bs-strategy="fixed"
                                    aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button type="button" class="dropdown-item"
                                            data-bs-toggle="modal" data-bs-target="#payModal-{{ $inv->id }}">
                                        <i class="bi bi-cash-coin me-2 opacity-60"></i>Mark as Paid
                                    </button>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('super.billing.invoices.retry', $inv) }}">
                                        @csrf
                                        <button class="dropdown-item">
                                            <i class="bi bi-arrow-clockwise me-2 opacity-60"></i>Retry Charge
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($invoices->hasPages())
    <div class="px-4 py-3 border-top d-flex justify-content-end">
        {{ $invoices->links() }}
    </div>
    @endif
</div>

@push('modals')
@foreach($invoices as $inv)
    @if($inv->status !== 'paid')
    <div class="modal fade" id="payModal-{{ $inv->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('super.billing.invoices.mark-paid', $inv) }}" class="modal-content text-start">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Mark Invoice as Paid</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center justify-content-between p-3 rounded mb-4"
                         style="background:var(--bs-secondary-bg);">
                        <div>
                            <div class="small text-muted">Tenant</div>
                            <div class="fw-semibold">{{ $inv->tenant?->name }}</div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted">Amount</div>
                            <div class="fw-bold fs-5">₱{{ number_format($inv->total, 2) }}</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Payment Method</label>
                        <select name="payment_gateway" class="form-select">
                            <option value="manual">Manual (cash / bank deposit)</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="stripe">Stripe</option>
                            <option value="paymongo">PayMongo</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Reference / Transaction #</label>
                        <input name="payment_reference" type="text" class="form-control" placeholder="e.g. BPI deposit slip 12345">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Paid At</label>
                        <input name="paid_at" type="datetime-local" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Marking paid extends the subscription's renewal date and lifts any tenant suspension.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Mark Paid
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endforeach
@endpush

@endsection
