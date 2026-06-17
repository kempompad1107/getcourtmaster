@extends('layouts.super')
@section('title', $tenant->name)

@section('content')

<x-page-header :title="$tenant->name" :back="route('super.tenants.index')" subtitle="Tenant detail">
    <x-slot name="actions">
        <a href="{{ route('super.tenants.edit', $tenant) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a href="{{ route('super.tenants.users', $tenant) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people me-1"></i>Users
        </a>
        <form method="POST" action="{{ route('super.tenants.impersonate', $tenant) }}" class="d-inline">
            @csrf
            <button class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-badge me-1"></i>Impersonate
            </button>
        </form>

        {{-- Status menu --}}
        <div class="dropdown d-inline-block">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-arrow-repeat me-1"></i>Status
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if($tenant->status !== 'active')
                    <li>
                        <form method="POST" action="{{ route('super.tenants.activate', $tenant) }}">
                            @csrf
                            <button class="dropdown-item text-success"><i class="bi bi-check-circle me-2"></i>Set Active</button>
                        </form>
                    </li>
                @endif
                @if($tenant->status !== 'trial')
                    <li>
                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#trialModal">
                            <i class="bi bi-hourglass-split me-2"></i>Set to Trial…
                        </button>
                    </li>
                @endif
                @if($tenant->status === 'trial')
                    <li>
                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#extendTrialModal">
                            <i class="bi bi-calendar-plus me-2"></i>Extend Trial…
                        </button>
                    </li>
                @endif
                @if($tenant->status !== 'suspended')
                    <li>
                        <form method="POST" action="{{ route('super.tenants.suspend', $tenant) }}">
                            @csrf
                            <button class="dropdown-item text-danger"><i class="bi bi-slash-circle me-2"></i>Suspend</button>
                        </form>
                    </li>
                @endif
                @if($tenant->status !== 'cancelled')
                    <li>
                        <form method="POST" action="{{ route('super.tenants.cancel', $tenant) }}" onsubmit="return confirm('Cancel this tenant? Their account will be marked cancelled.');">
                            @csrf
                            <button class="dropdown-item text-secondary"><i class="bi bi-x-circle me-2"></i>Cancel Account</button>
                        </form>
                    </li>
                @endif
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('super.tenants.destroy', $tenant) }}" onsubmit="return confirm('Soft-delete this tenant? They will disappear from the list but the data is recoverable.');">
                        @csrf @method('DELETE')
                        <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete (soft)</button>
                    </form>
                </li>
            </ul>
        </div>
    </x-slot>
</x-page-header>

{{-- Profile card --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
                <h5 class="fw-semibold mb-1">{{ $tenant->name }}</h5>
                <p class="text-muted small mb-1">
                    <i class="bi bi-envelope me-1"></i>{{ $tenant->email }}
                    @if($tenant->phone)
                        &bull; <i class="bi bi-phone me-1"></i>{{ $tenant->phone }}
                    @endif
                </p>
                @if($tenant->address)
                <p class="text-muted small mb-0">
                    <i class="bi bi-geo-alt me-1"></i>{{ $tenant->address }}{{ $tenant->city ? ', ' . $tenant->city : '' }}
                </p>
                @endif
                @if($tenant->trial_ends_at)
                    <p class="small mt-2 mb-0">
                        <i class="bi bi-hourglass-split me-1 text-warning"></i>
                        Trial ends: <strong>{{ $tenant->trial_ends_at->format('M j, Y') }}</strong>
                        ({{ $tenant->trial_ends_at->diffForHumans() }})
                    </p>
                @endif
            </div>
            <div>
                <x-badge :status="match($tenant->status) { 'active' => 'active', 'suspended' => 'cancelled', 'trial' => 'pending', 'cancelled' => 'cancelled', default => 'neutral' }">{{ ucfirst($tenant->status) }}</x-badge>
            </div>
        </div>
    </div>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-3"><x-stat-card label="Users" :value="$tenant->users_count" icon="bi-people" color="blue" :href="route('super.tenants.users', $tenant)"/></div>
    <div class="col-6 col-sm-3"><x-stat-card label="Courts" :value="$tenant->courts_count" icon="bi-grid" color="emerald"/></div>
    <div class="col-6 col-sm-3"><x-stat-card label="Total Bookings" :value="$tenant->bookings_count" icon="bi-calendar-check" color="purple"/></div>
    <div class="col-6 col-sm-3"><x-stat-card label="Current Plan" :value="ucfirst($tenant->plan)" icon="bi-credit-card" color="amber"/></div>
</div>

<div class="row g-4">
    {{-- Enabled features --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Enabled Features</h6>
                <a href="{{ route('super.tenants.edit', $tenant) }}#features" class="btn btn-link btn-sm p-0">Edit</a>
            </div>
            <div class="card-body">
                @if(!empty($tenant->features))
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($tenant->features as $feature)
                            <span class="badge text-bg-success">{{ str_replace('_', ' ', ucfirst($feature)) }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small mb-0">No feature overrides. Tenant gets defaults from their plan.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Active subscription --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Current Subscription</h6>
                <button class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#changePlanModal">
                    {{ $tenant->activeSubscription ? 'Change…' : 'Attach plan…' }}
                </button>
            </div>
            <div class="card-body">
                @if($tenant->activeSubscription)
                    <dl class="row g-2 mb-3 small">
                        <dt class="col-5 text-muted fw-normal">Plan</dt>
                        <dd class="col-7 fw-medium mb-0">{{ $tenant->activeSubscription->plan->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Status</dt>
                        <dd class="col-7 fw-medium mb-0 text-capitalize">{{ $tenant->activeSubscription->status }}</dd>
                        <dt class="col-5 text-muted fw-normal">Billing cycle</dt>
                        <dd class="col-7 fw-medium mb-0">{{ ucfirst($tenant->activeSubscription->billing_cycle ?? '—') }}</dd>
                        <dt class="col-5 text-muted fw-normal">Amount</dt>
                        <dd class="col-7 fw-medium mb-0">₱{{ number_format($tenant->activeSubscription->amount, 2) }}</dd>
                        <dt class="col-5 text-muted fw-normal">Renews</dt>
                        <dd class="col-7 fw-medium mb-0">{{ $tenant->activeSubscription->renews_at?->format('M j, Y') ?? '—' }}</dd>
                    </dl>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('super.billing.subscriptions.generate', $tenant->activeSubscription) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-file-plus me-1"></i>Generate Invoice</button>
                        </form>
                        @if($tenant->activeSubscription->status !== 'cancelled')
                        <form method="POST" action="{{ route('super.billing.subscriptions.cancel', $tenant->activeSubscription) }}"
                              onsubmit="return confirm('Cancel this subscription? The tenant will keep access until ends_at.');">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Cancel Subscription</button>
                        </form>
                        @endif
                    </div>
                @else
                    <p class="text-muted small mb-0">No active subscription. Click "Attach plan…" above to start one.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Recent invoices --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Recent Invoices</h6>
                <a href="{{ route('super.billing.invoices', ['tenant_id' => $tenant->id]) }}" class="btn btn-link btn-sm p-0">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Invoice #</th><th class="text-end">Total</th><th>Status</th><th>Due</th><th>Paid</th></tr></thead>
                    <tbody>
                        @forelse($recentInvoices as $inv)
                        <tr>
                            <td><code class="small">{{ $inv->invoice_number }}</code></td>
                            <td class="text-end">₱{{ number_format($inv->total, 2) }}</td>
                            <td>
                                <x-badge :status="match($inv->status) { 'paid' => 'active', 'pending' => 'pending', 'failed' => 'cancelled', 'refunded' => 'cancelled', 'draft' => 'pending', default => 'neutral' }">{{ ucfirst($inv->status) }}</x-badge>
                            </td>
                            <td class="small text-muted">{{ $inv->due_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="small text-muted">{{ $inv->paid_at?->format('M j, Y') ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted small py-3">No invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

{{-- Modals rendered outside .page-enter to avoid transform stacking-context issues --}}
@push('modals')

{{-- Change plan modal --}}
<div class="modal fade" id="changePlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('super.billing.tenants.change-plan', $tenant) }}" class="modal-content text-start">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title">{{ $tenant->activeSubscription ? 'Change' : 'Attach' }} Plan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small">Plan</label>
                    <select name="plan_id" class="form-select" required>
                        @foreach($plans as $p)
                            <option value="{{ $p->id }}" @selected($tenant->activeSubscription?->plan_id === $p->id)>
                                {{ $p->name }} — ₱{{ number_format($p->price_monthly, 2) }}/mo or ₱{{ number_format($p->price_yearly, 2) }}/yr
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Billing cycle</label>
                    <select name="billing_cycle" class="form-select" required>
                        <option value="monthly" @selected($tenant->activeSubscription?->billing_cycle === 'monthly')>Monthly</option>
                        <option value="yearly" @selected($tenant->activeSubscription?->billing_cycle === 'yearly')>Yearly</option>
                    </select>
                </div>
                <p class="small text-muted mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    This updates <code>renews_at</code> to the new cycle and syncs <code>tenants.plan</code>.
                    No invoice is generated automatically — use "Generate Invoice" if needed.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary btn-sm">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Set Trial modal --}}
<div class="modal fade" id="trialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('super.tenants.set-trial', $tenant) }}" class="modal-content text-start">
            @csrf
            <div class="modal-header"><h6 class="modal-title">Set to Trial</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label small">Trial length (days)</label>
                <input type="number" name="trial_days" value="14" min="1" max="365" class="form-control" required>
                <p class="small text-muted mt-2 mb-0">Sets <code>trial_ends_at</code> to today + N days and status to <code>trial</code>.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-outline-secondary btn-sm">Set Trial</button>
            </div>
        </form>
    </div>
</div>

{{-- Extend Trial modal --}}
<div class="modal fade" id="extendTrialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('super.tenants.extend-trial', $tenant) }}" class="modal-content text-start">
            @csrf
            <div class="modal-header"><h6 class="modal-title">Extend Trial</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label small">Extend by (days)</label>
                <input type="number" name="days" value="7" min="1" max="365" class="form-control" required>
                <p class="small text-muted mt-2 mb-0">Current trial ends: <strong>{{ $tenant->trial_ends_at?->format('M j, Y') ?? 'not set' }}</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary btn-sm">Extend</button>
            </div>
        </form>
    </div>
</div>

@endpush
