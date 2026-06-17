<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #1f2937; font-size: 12px; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #059669; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; }
        .right { text-align: right; }
        .totals td { border: none; padding: 4px 8px; }
        .stamp { display:inline-block; padding:6px 10px; border:2px solid; border-radius:4px; font-weight:bold; }
        .paid { color:#059669; }
        .unpaid { color:#b45309; }
    </style>
</head>
<body>
    <table style="border:none;">
        <tr style="border:none;">
            <td style="border:none;">
                <h1>{{ config('app.name', 'CourtMaster') }}</h1>
                <div class="muted">Subscription Invoice</div>
            </td>
            <td style="border:none;" class="right">
                <div><strong>#{{ $invoice->invoice_number }}</strong></div>
                <div class="muted">Issued: {{ $invoice->created_at->format('M d, Y') }}</div>
                @if ($invoice->due_at)
                    <div class="muted">Due: {{ $invoice->due_at->format('M d, Y') }}</div>
                @endif
                <div class="stamp {{ $invoice->status === 'paid' ? 'paid' : 'unpaid' }}">
                    {{ strtoupper($invoice->status) }}
                </div>
            </td>
        </tr>
    </table>

    <table>
        <tr><th>Billed to</th><th>Plan</th></tr>
        <tr>
            <td>
                <strong>{{ $invoice->tenant->name }}</strong><br>
                {{ $invoice->tenant->email ?? '' }}
            </td>
            <td>
                <strong>{{ $invoice->subscription?->plan?->name ?? 'Subscription' }}</strong><br>
                <span class="muted">{{ $invoice->subscription?->billing_cycle ?? '' }}</span>
            </td>
        </tr>
    </table>

    <table>
        <tr><th>Description</th><th class="right">Amount</th></tr>
        <tr>
            <td>{{ $invoice->subscription?->plan?->name ?? 'Subscription' }} subscription</td>
            <td class="right">₱{{ number_format($invoice->amount, 2) }}</td>
        </tr>
    </table>

    <table class="totals" style="width: 320px; float: right; margin-top: 8px;">
        <tr><td>Subtotal</td><td class="right">₱{{ number_format($invoice->amount, 2) }}</td></tr>
        <tr><td>Tax</td><td class="right">₱{{ number_format($invoice->tax, 2) }}</td></tr>
        <tr><td><strong>Total</strong></td><td class="right"><strong>₱{{ number_format($invoice->total, 2) }}</strong></td></tr>
        @if ($invoice->paid_at)
            <tr><td colspan="2" class="muted right">Paid {{ $invoice->paid_at->format('M d, Y') }} · {{ $invoice->payment_gateway }}</td></tr>
        @endif
    </table>
</body>
</html>
