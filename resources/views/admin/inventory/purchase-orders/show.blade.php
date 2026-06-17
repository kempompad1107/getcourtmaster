@extends('layouts.app')
@section('title', 'PO ' . $po->po_number)

@section('content')

@php
    $canReceive = !in_array($po->status, ['received', 'cancelled']);
@endphp

<x-page-header :title="'Purchase Order ' . $po->po_number">
    <x-slot name="actions">
        <x-badge :status="match($po->status) { 'draft' => 'pending', 'sent' => 'info', 'received' => 'active', 'cancelled' => 'cancelled', default => 'neutral' }">{{ ucfirst($po->status) }}</x-badge>
        <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </x-slot>
</x-page-header>

<div class="row g-4">

    {{-- Meta --}}
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Order Details</h6></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Supplier</dt>
                    <dd class="col-7">{{ $po->supplier?->name ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Expected</dt>
                    <dd class="col-7">{{ $po->expected_at?->format('M d, Y') ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Received</dt>
                    <dd class="col-7">{{ $po->received_at?->format('M d, Y') ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Subtotal</dt>
                    <dd class="col-7">₱{{ number_format($po->subtotal, 2) }}</dd>

                    <dt class="col-5 text-muted fw-normal">Tax</dt>
                    <dd class="col-7">₱{{ number_format($po->tax_amount, 2) }}</dd>

                    <dt class="col-5 text-muted fw-normal">Total</dt>
                    <dd class="col-7 fw-semibold">₱{{ number_format($po->total, 2) }}</dd>
                </dl>

                @if($po->notes)
                <hr class="my-3">
                <p class="small text-muted mb-0 fst-italic">{{ $po->notes }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Items + receive form --}}
    <div class="col-12 col-lg-8">
        <form method="POST" action="{{ route('admin.purchase-orders.receive', $po) }}">
            @csrf
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-semibold">Line Items</h6>
                    <span class="badge text-bg-secondary">{{ $po->items->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Ordered</th>
                                <th class="text-end">Received</th>
                                @if($canReceive)
                                <th style="width:140px">Receive Now</th>
                                @endif
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($po->items as $item)
                            @php $outstanding = max(0, $item->quantity_ordered - $item->quantity_received); @endphp
                            <tr>
                                <td class="small">{{ $item->product?->name ?? '—' }}</td>
                                <td class="text-end small">{{ $item->quantity_ordered }}</td>
                                <td class="text-end small">{{ $item->quantity_received }}</td>
                                @if($canReceive)
                                <td>
                                    <input type="number" name="received[{{ $item->id }}]"
                                           min="0" max="{{ $outstanding }}" step="1"
                                           value="{{ $outstanding }}"
                                           class="form-control form-control-sm">
                                </td>
                                @endif
                                <td class="text-end small">₱{{ number_format($item->unit_cost, 2) }}</td>
                                <td class="text-end small fw-medium">₱{{ number_format($item->line_total, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $canReceive ? 6 : 5 }}">
                                    <x-empty-state title="No items" icon="bi-box-seam"/>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($canReceive && $po->items->isNotEmpty())
                <div class="card-footer d-flex justify-content-end">
                    <button type="submit" class="btn btn-success btn-sm"
                            onclick="return confirm('Receive these quantities into stock?')">
                        <i class="bi bi-box-arrow-in-down me-1"></i>Receive Stock
                    </button>
                </div>
                @endif
            </div>
        </form>
    </div>
</div>

@endsection
