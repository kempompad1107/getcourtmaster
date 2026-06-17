<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt #{{ $order->order_number }}</title>
    <style>
        /* 80mm thermal receipt — 72mm printable width */
        @page { size: 80mm auto; margin: 0; }
        body { width: 72mm; margin: 4mm auto; font-family: 'Courier New', monospace; font-size: 11px; color: #000; }
        .center { text-align: center; }
        .right  { text-align: right; }
        .row    { display: flex; justify-content: space-between; }
        hr      { border: 0; border-top: 1px dashed #000; margin: 4px 0; }
        table   { width: 100%; border-collapse: collapse; }
        td      { padding: 1px 0; vertical-align: top; }
        .qty    { width: 22px; }
        .price  { text-align: right; }
        h2      { font-size: 13px; margin: 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">

<div class="center">
    <h2>{{ $order->tenant->name ?? config('app.name') }}</h2>
    <div>Order #{{ $order->order_number }}</div>
    <div>{{ $order->created_at->format('Y-m-d H:i') }}</div>
    @if ($order->cashier) <div>Cashier: {{ $order->cashier->name }}</div> @endif
</div>

<hr>

<table>
    @foreach ($order->items as $i)
        <tr>
            <td class="qty">{{ $i->quantity }}×</td>
            <td>{{ $i->name }}</td>
            <td class="price">{{ number_format($i->subtotal, 2) }}</td>
        </tr>
    @endforeach
</table>

<hr>

<div class="row"><span>Subtotal</span><span>{{ number_format($order->subtotal, 2) }}</span></div>
@if ($order->discount_amount > 0)
    <div class="row"><span>Discount</span><span>– {{ number_format($order->discount_amount, 2) }}</span></div>
@endif
<div class="row"><span>Tax</span><span>{{ number_format($order->tax_amount, 2) }}</span></div>
<div class="row" style="font-weight:bold;font-size:13px;"><span>TOTAL</span><span>{{ number_format($order->total, 2) }}</span></div>

<hr>

@foreach ($order->posPayments as $p)
    <div class="row">
        <span>{{ ucfirst($p->method) }}{{ $p->reference ? ' ('.$p->reference.')' : '' }}</span>
        <span>{{ number_format($p->amount, 2) }}</span>
    </div>
@endforeach
@if ($order->change_amount > 0)
    <div class="row"><span>Change</span><span>{{ number_format($order->change_amount, 2) }}</span></div>
@endif

<hr>

<div class="center">
    Thank you!
    @if ($order->status !== 'completed')
        <div><strong>** BALANCE DUE **</strong></div>
    @endif
</div>

<div class="no-print center" style="margin-top:12px;">
    <button onclick="window.print()">Print</button>
</div>
</body>
</html>
