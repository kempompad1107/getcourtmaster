<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt — {{ $booking->booking_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; max-width: 320px; margin: 0 auto; padding: 20px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; margin: 2px 0; }
        .total-row { font-weight: bold; font-size: 14px; margin: 4px 0; }
        h1 { font-size: 18px; font-weight: bold; margin-bottom: 2px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            @page { margin: 5mm; }
        }
    </style>
</head>
<body>
    <div class="center">
        <h1>{{ $booking->court->tenant->name }}</h1>
        <p>{{ $booking->court->tenant->address }}</p>
        <p>{{ $booking->court->tenant->phone }}</p>
        <p>{{ $booking->court->tenant->email }}</p>
    </div>

    <div class="divider"></div>

    <div class="center bold">BOOKING RECEIPT</div>

    <div class="divider"></div>

    <div class="row"><span>Receipt #</span><span>{{ $booking->booking_number }}</span></div>
    <div class="row"><span>Date</span><span>{{ now()->format('m/d/Y H:i') }}</span></div>
    <div class="row"><span>Cashier</span><span>{{ auth()->user()->name }}</span></div>

    <div class="divider"></div>

    <div class="row"><span>Customer</span><span>{{ $booking->customer?->name ?? 'Walk-in' }}</span></div>
    <div class="row"><span>Court</span><span>{{ $booking->court->name }}</span></div>
    <div class="row"><span>Date</span><span>{{ $booking->booking_date->format('m/d/Y') }}</span></div>
    <div class="row"><span>Time</span><span>{{ \Carbon\Carbon::parse($booking->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($booking->end_time)->format('g:i A') }}</span></div>
    <div class="row"><span>Duration</span><span>{{ $booking->duration_minutes }} min</span></div>

    <div class="divider"></div>

    <div class="row"><span>Court rental</span><span>₱{{ number_format($booking->base_amount, 2) }}</span></div>
    @if($booking->discount_amount > 0)
    <div class="row"><span>Discount</span><span>-₱{{ number_format($booking->discount_amount, 2) }}</span></div>
    @endif
    @if($booking->tax_amount > 0)
    <div class="row"><span>Tax ({{ $booking->court->tenant->getSetting('tax_rate') }}%)</span><span>₱{{ number_format($booking->tax_amount, 2) }}</span></div>
    @endif
    @if($booking->overtime_amount > 0)
    <div class="row"><span>Overtime</span><span>₱{{ number_format($booking->overtime_amount, 2) }}</span></div>
    @endif

    <div class="divider"></div>

    <div class="row total-row"><span>TOTAL</span><span>₱{{ number_format($booking->total_amount, 2) }}</span></div>
    <div class="row"><span>Paid</span><span>₱{{ number_format($booking->paid_amount, 2) }}</span></div>
    @if($booking->balance_due > 0)
    <div class="row bold"><span>Balance Due</span><span>₱{{ number_format($booking->balance_due, 2) }}</span></div>
    @endif

    @if($booking->payments->isNotEmpty())
    <div class="divider"></div>
    @foreach($booking->payments as $payment)
    <div class="row"><span>{{ ucfirst($payment->method) }}</span><span>₱{{ number_format($payment->amount, 2) }}</span></div>
    @endforeach
    @endif

    <div class="divider"></div>

    @if($booking->qr_code)
    <div class="center" style="margin: 8px 0;">
        <img src="{{ $booking->qr_code_image }}" style="width: 80px; height: 80px;">
    </div>
    @endif

    @php $graceMinutes = (int) ($booking->court->tenant->settings['grace_period_minutes'] ?? 5); @endphp
    @if($graceMinutes > 0)
    <div class="center" style="margin-top: 8px; font-size: 10px;">
        <p>Grace period: {{ $graceMinutes }} min free after end time.</p>
        <p>Overtime billed at court rate after grace.</p>
    </div>
    <div class="divider"></div>
    @endif

    <div class="center" style="margin-top: 12px;">
        <p>Thank you for playing with us!</p>
        <p>See you on the court.</p>
    </div>

    <div class="center no-print" style="margin-top: 20px;">
        <button onclick="window.print()" style="background: #059669; color: #fff; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-size: 14px;">
            Print Receipt
        </button>
    </div>
</body>
</html>
