@extends('layouts.app')
@section('title', 'POS Receipt #' . $order->order_number)

@section('content')

<div class="row justify-content-center">
    <div class="col-12" style="max-width:380px">
        <div class="card font-monospace">
            <div class="card-body">
                {{-- Header --}}
                <div class="text-center mb-3">
                    <h6 class="fw-bold mb-0">{{ auth()->user()->tenant->name }}</h6>
                    <small class="text-muted">{{ $order->created_at->format('M j, Y  g:i A') }}</small>
                </div>

                <hr class="border-dashed my-2">

                <div class="small mb-3">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Order #</span>
                        <span class="fw-medium">{{ $order->order_number }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Cashier</span>
                        <span>{{ $order->cashier->name ?? 'System' }}</span>
                    </div>
                    @if($order->customer)
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Customer</span>
                        <span>{{ $order->customer->name }}</span>
                    </div>
                    @endif
                </div>

                <hr class="border-dashed my-2">

                {{-- Items --}}
                <div class="small mb-3">
                    @foreach($order->items as $item)
                    <div class="d-flex justify-content-between">
                        <span>
                            {{ $item->name }}
                            @if($item->quantity > 1)
                            <span class="text-muted"> x{{ $item->quantity }}</span>
                            @endif
                        </span>
                        <span>₱{{ number_format($item->quantity * $item->unit_price, 2) }}</span>
                    </div>
                    @endforeach
                </div>

                <hr class="border-dashed my-2">

                {{-- Totals --}}
                <div class="small mb-2">
                    <div class="d-flex justify-content-between text-muted">
                        <span>Subtotal</span>
                        <span>₱{{ number_format($order->subtotal, 2) }}</span>
                    </div>
                    @if($order->discount_amount > 0)
                    <div class="d-flex justify-content-between text-danger">
                        <span>Discount</span>
                        <span>-₱{{ number_format($order->discount_amount, 2) }}</span>
                    </div>
                    @endif
                    @if($order->tax_amount > 0)
                    <div class="d-flex justify-content-between text-muted">
                        <span>Tax</span>
                        <span>₱{{ number_format($order->tax_amount, 2) }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between fw-bold border-top mt-1 pt-1">
                        <span>TOTAL</span>
                        <span>₱{{ number_format($order->total, 2) }}</span>
                    </div>
                </div>

                <hr class="border-dashed my-2">

                {{-- Payments --}}
                <div class="small mb-3">
                    @foreach($order->posPayments as $payment)
                    <div class="d-flex justify-content-between text-muted">
                        <span class="text-capitalize">{{ str_replace('_', ' ', $payment->method) }}</span>
                        <span>₱{{ number_format($payment->amount, 2) }}</span>
                    </div>
                    @endforeach
                    @if($order->change_amount > 0)
                    <div class="d-flex justify-content-between fw-medium">
                        <span class="text-muted">Change</span>
                        <span>₱{{ number_format($order->change_amount, 2) }}</span>
                    </div>
                    @endif
                </div>

                <hr class="border-dashed my-2">

                <div class="text-center small text-muted">
                    <p class="mb-0">Thank you for playing with us! This receipt is unofficial</p>
                    <p class="mb-0">Powered by CourtMaster</p>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 mt-3 d-print-none">
            <button onclick="window.print()" class="btn btn-primary flex-fill">
                <i class="bi bi-printer me-1"></i>Print Receipt
            </button>
            <a href="{{ route('admin.pos.index') }}" class="btn btn-outline-secondary flex-fill">
                New Order
            </a>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.border-dashed { border-style: dashed !important; }
@media print {
    .d-print-none { display: none !important; }
    .sidebar, .topbar { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
}
</style>
@endpush
