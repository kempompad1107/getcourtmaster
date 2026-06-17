@extends('layouts.app')
@section('title', 'Membership Detail')

@section('content')

<x-page-header title="Membership Detail" :back="route('admin.memberships.index')">
    <x-slot name="actions">
        <x-badge :status="$membership->status">{{ ucfirst($membership->status) }}</x-badge>
    </x-slot>
</x-page-header>

{{-- Header card --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-start gap-4 flex-wrap">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold fs-4 badge-soft-purple"
                 style="width:64px;height:64px">
                {{ strtoupper(substr($membership->user->name, 0, 1)) }}
            </div>
            <div class="flex-grow-1 min-w-0">
                <h5 class="fw-semibold mb-1">{{ $membership->user->name }}</h5>
                <div class="small text-muted mb-2">
                    <i class="bi bi-envelope me-1"></i>{{ $membership->user->email }}
                </div>
                <span class="badge badge-soft-purple">{{ $membership->plan->name }}</span>
                @if($membership->plan->is_vip)
                <span class="badge badge-soft-warning ms-1">VIP</span>
                @endif
            </div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-6 col-sm-4">
                <p class="text-muted small mb-0">Plan</p>
                <p class="fw-semibold mb-0">{{ $membership->plan->name }}</p>
            </div>
            <div class="col-6 col-sm-4">
                <p class="text-muted small mb-0">Started</p>
                <p class="fw-semibold mb-0">{{ $membership->starts_at->format('M j, Y') }}</p>
            </div>
            <div class="col-6 col-sm-4">
                <p class="text-muted small mb-0">
                    {{ $membership->status === 'cancelled' ? 'Cancelled' : 'Expires' }}
                </p>
                <p class="fw-semibold mb-0">{{ $membership->expires_at?->format('M j, Y') ?? '—' }}</p>
            </div>
            <div class="col-6 col-sm-4">
                <p class="text-muted small mb-0">Court Time Left</p>
                <p class="fw-semibold mb-0">{{ $membership->credits_label }}</p>
            </div>
            <div class="col-6 col-sm-4">
                <p class="text-muted small mb-0">Membership #</p>
                <p class="fw-semibold mb-0 font-monospace small">{{ $membership->membership_number }}</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Plan features --}}
    <div class="col-12 col-lg-5">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Plan Features</h6></div>
            <div class="list-group list-group-flush">
                @foreach([
                    ['Price', '₱' . number_format($membership->plan->price, 2) . ' / ' . $membership->plan->billing_cycle],
                    ['Court time', $membership->plan->court_hours . ' hours / cycle'],
                    ['Discount', $membership->plan->discount_percent ? $membership->plan->discount_percent . '%' : 'None'],
                    ['VIP', $membership->plan->is_vip ? 'Yes' : 'No'],
                ] as [$label, $value])
                <div class="list-group-item d-flex justify-content-between py-2">
                    <span class="text-muted small">{{ $label }}</span>
                    <span class="small fw-medium">{{ $value }}</span>
                </div>
                @endforeach
            </div>
            @if(!empty($membership->plan->perks))
            <div class="card-footer">
                <p class="text-muted small mb-2">Perks</p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($membership->plan->perks as $perk)
                    <span class="badge text-bg-success fw-normal">{{ $perk }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="d-flex gap-2">
            @if($membership->status === 'active')
            <form method="POST" action="{{ route('admin.memberships.cancel', $membership) }}"
                  onsubmit="return confirm('Cancel this membership? The member will lose access at the end of the billing period.')">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-x-circle me-1"></i>Cancel Membership
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Payment history --}}
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Payment History</h6></div>
            @if($membership->payments->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($membership->payments as $payment)
                        <tr>
                            <td class="small">{{ $payment->paid_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="small fw-medium">₱{{ number_format($payment->amount, 2) }}</td>
                            <td class="small">{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                            <td>
                                <x-badge :status="$payment->status === 'paid' ? 'active' : 'cancelled'">{{ ucfirst($payment->status) }}</x-badge>
                            </td>
                            <td class="small font-monospace text-muted">{{ $payment->receipt_number ?? $payment->payment_number ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="card-body">
                <x-empty-state title="No payment records" icon="bi-receipt"/>
            </div>
            @endif
        </div>

        {{-- Membership transaction ledger --}}
        <div class="card mt-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Activity Ledger</h6>
                <span class="small text-muted">{{ $membership->transactions->count() }} {{ \Illuminate\Support\Str::plural('entry', $membership->transactions->count()) }}</span>
            </div>
            @if($membership->transactions->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="text-end">Credits</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($membership->transactions->sortByDesc('created_at') as $tx)
                        @php
                            $typeClass = [
                                'purchase'    => 'success',
                                'renewal'     => 'primary',
                                'credit_use'  => 'secondary',
                                'freeze'      => 'info',
                                'cancel'      => 'danger',
                                'top_up'      => 'success',
                            ][$tx->type] ?? 'secondary';
                        @endphp
                        <tr>
                            <td class="small text-muted" style="white-space:nowrap">{{ $tx->created_at->format('M j, Y g:i A') }}</td>
                            <td>
                                <span class="badge bg-{{ $typeClass }}-subtle text-{{ $typeClass }} text-capitalize">{{ str_replace('_', ' ', $tx->type) }}</span>
                            </td>
                            <td class="small">{{ $tx->description ?: '—' }}</td>
                            <td class="text-end small fw-medium {{ ($tx->credits_change ?? 0) > 0 ? 'text-success' : (($tx->credits_change ?? 0) < 0 ? 'text-danger' : 'text-muted') }}">
                                @if($tx->credits_change)
                                    {{ $tx->credits_change > 0 ? '+' : '' }}{{ $tx->credits_change }}m
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end small fw-medium">
                                @if($tx->amount)
                                    ₱{{ number_format($tx->amount, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="card-body">
                <x-empty-state title="No activity yet" icon="bi-clock-history"/>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection
