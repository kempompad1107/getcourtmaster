@extends('layouts.app')
@section('title', 'Memberships')

@push('styles')
<style>
    /* ── Memberships list — polish + mobile card stacking ── */
    .mb-avatar {
        width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 700; font-size: .82rem;
        color: #fff; background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    }
    .mb-table tbody tr { transition: background-color .15s; }
    @media (max-width: 767.98px) {
        .mb-table thead { display: none; }
        .mb-table, .mb-table tbody, .mb-table tr, .mb-table td { display: block; width: 100%; }
        .mb-table tr {
            border: 1px solid var(--bs-border-color); border-radius: .85rem;
            padding: .35rem .9rem; margin: .75rem 0; background: var(--bs-card-bg);
        }
        .mb-table td {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border: 0; padding: .5rem 0; text-align: right;
        }
        .mb-table td + td { border-top: 1px solid var(--bs-border-color); }
        .mb-table td::before {
            content: attr(data-label); text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
        .mb-table td.bk-cell-empty::before { content: none; }
    }
</style>
@endpush

@section('content')

<x-page-header title="Memberships">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.memberships.index') }}"
               class="btn btn-sm {{ !request('view') ? 'btn-secondary' : 'btn-outline-secondary' }}">
                Active Members
            </a>
            <a href="{{ route('admin.memberships.plans') }}"
               class="btn btn-sm btn-outline-secondary">Plans</a>
        </div>
        <button type="button" class="btn btn-primary btn-sm"
                data-bs-toggle="modal" data-bs-target="#assign-membership">
            <i class="bi bi-plus-lg me-1"></i>Assign Membership
        </button>
        <a href="{{ route('admin.memberships.plans') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-grid me-1"></i>Manage Plans
        </a>
    </x-slot>
</x-page-header>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-3">
        <x-stat-card label="Active"       :value="$stats['active']"                     icon="bi-credit-card" color="emerald"/>
    </div>
    <div class="col-6 col-sm-3">
        <x-stat-card label="Expiring Soon" :value="$stats['expiring_soon']"              icon="bi-clock"       color="amber"/>
    </div>
    <div class="col-6 col-sm-3">
        <x-stat-card label="Expired"      :value="$stats['expired']"                     icon="bi-x-circle"    color="red"/>
    </div>
    <div class="col-6 col-sm-3">
        <x-stat-card label="MRR"          :value="'₱'.number_format($stats['mrr'])"     icon="bi-graph-up"    color="emerald" :small="true"/>
    </div>
</div>

{{-- Unified filter bar --}}
<x-filter-bar placeholder="Search member..."
              :active-count="(int) request()->filled('status') + (int) request()->filled('plan_id')"
              :clear="route('admin.memberships.index')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <option value="active"    @selected(request('status') === 'active')>Active</option>
                <option value="frozen"    @selected(request('status') === 'frozen')>Frozen</option>
                <option value="expired"   @selected(request('status') === 'expired')>Expired</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
            </select>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Plan</label>
            <select name="plan_id" class="form-select form-select-sm">
                <option value="">All plans</option>
                @foreach($plans as $plan)
                <option value="{{ $plan->id }}" @selected(request('plan_id') == $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>
        </div>
    </x-slot>
</x-filter-bar>

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table mb-table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Member</th>
                    <th>Plan</th>
                    <th>Started</th>
                    <th>Expires</th>
                    <th>Credits</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($memberships as $membership)
                <tr>
                    <td data-label="Member">
                        <div class="d-flex align-items-center gap-2">
                            <div class="mb-avatar">{{ strtoupper(substr($membership->user->name, 0, 1)) }}</div>
                            <div class="min-w-0">
                                <p class="mb-0 small fw-semibold">{{ $membership->user->name }}</p>
                                <small class="text-muted font-monospace">{{ $membership->membership_number }}</small>
                            </div>
                        </div>
                    </td>
                    <td data-label="Plan">
                        <div class="d-flex align-items-center gap-2 justify-content-end justify-content-md-start">
                            <span class="small fw-medium">{{ $membership->plan->name }}</span>
                            @if($membership->plan->is_vip)
                            <span class="badge rounded-pill bg-warning-subtle text-warning"><i class="bi bi-star-fill me-1"></i>VIP</span>
                            @endif
                        </div>
                    </td>
                    <td data-label="Started" class="small">{{ $membership->starts_at->format('M j, Y') }}</td>
                    <td data-label="Expires">
                        <div>
                            <p class="mb-0 small">{{ $membership->expires_at->format('M j, Y') }}</p>
                            @if($membership->isActive() && $membership->getDaysRemainingAttribute() <= 7)
                            <small class="text-warning">{{ $membership->getDaysRemainingAttribute() }} days left</small>
                            @endif
                        </div>
                    </td>
                    <td data-label="Credits" class="small">{{ $membership->credits_label }}</td>
                    <td data-label="Status"><x-badge :status="$membership->status">{{ ucfirst($membership->status) }}</x-badge></td>
                    <td data-label="" class="bk-cell-empty text-end">
                        <a href="{{ route('admin.memberships.show', $membership) }}"
                           class="btn btn-outline-primary btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="bk-cell-empty">
                        <x-empty-state title="No memberships found" icon="bi-credit-card"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($memberships->hasPages())
    <div class="card-footer">
        {{ $memberships->withQueryString()->links() }}
    </div>
    @endif
</div>

{{-- Assign Membership Modal --}}
<x-modal name="assign-membership" title="Assign Membership">
    <form method="POST" action="{{ route('admin.memberships.store') }}" id="assign-membership-form">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Customer</label>
                <select name="customer_id" required class="form-select" id="assign-customer-select">
                    <option value="">Search customer by name or email...</option>
                    @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }} — {{ $customer->email }}</option>
                    @endforeach
                </select>
                @if($customers->isEmpty())
                <div class="form-text text-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    No customers found. <a href="{{ route('admin.customers.create') }}">Create a customer first.</a>
                </div>
                @endif
            </div>
            <div class="col-12">
                <label class="form-label">Membership Plan</label>
                <select name="plan_id" required class="form-select">
                    <option value="">Select a plan...</option>
                    @foreach($plans as $plan)
                    <option value="{{ $plan->id }}">
                        {{ $plan->name }} — ₱{{ number_format($plan->price) }}/{{ $plan->billing_cycle }}
                        @if($plan->is_vip) [VIP] @endif
                    </option>
                    @endforeach
                </select>
                @if($plans->isEmpty())
                <div class="form-text text-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    No plans found. <a href="{{ route('admin.memberships.plans') }}">Create a plan first.</a>
                </div>
                @endif
            </div>
            <div class="col-12">
                <label class="form-label">Payment Method</label>
                <select name="payment_method" required class="form-select">
                    <option value="cash">Cash at the desk</option>
                    <option value="wallet">Customer's Wallet (auto-deduct)</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                    <option value="card">Credit / Debit Card</option>
                    <option value="bank_transfer">Bank Transfer</option>
                </select>
                <div class="form-text small">
                    Wallet requires sufficient balance — short balances throw an error before the membership is created.
                </div>
            </div>
            @if($promotions->isNotEmpty())
            <div class="col-12">
                <label class="form-label">Promotion <span class="text-muted fw-normal">(optional)</span></label>
                <select name="promo_code" class="form-select">
                    <option value="">No promotion</option>
                    @foreach($promotions as $promo)
                    <option value="{{ $promo->code }}">
                        {{ $promo->name }} ({{ $promo->code }}) —
                        @if($promo->type === 'percentage')
                            {{ $promo->value }}% off
                        @else
                            ₱{{ number_format($promo->value) }} off
                        @endif
                    </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-12">
                <div class="form-check">
                    <input type="checkbox" name="auto_renew" value="1" id="auto_renew"
                           class="form-check-input">
                    <label class="form-check-label" for="auto_renew">Auto-renew membership</label>
                </div>
            </div>
        </div>
    </form>

    <x-slot name="footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="assign-membership-form" class="btn btn-primary"
                {{ ($customers->isEmpty() || $plans->isEmpty()) ? 'disabled' : '' }}>
            <i class="bi bi-plus-lg me-1"></i>Assign Membership
        </button>
    </x-slot>
</x-modal>

@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .ts-wrapper.form-select { padding: 0; }
    .ts-wrapper .ts-control { border: none; background: transparent; min-height: calc(1.5em + 0.75rem + 2px); }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('assign-customer-select');
        if (el && window.TomSelect) {
            new TomSelect(el, {
                maxOptions: 500,
                searchField: ['text'],
                placeholder: 'Search customer by name or email...',
            });
        }
    });
</script>
@endpush
