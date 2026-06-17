<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $payment->receipt_number ?? $payment->id }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #1f2937; font-size: 12px; }
        h1 { font-size: 18px; margin: 0; color: #059669; }
        .small { color: #6b7280; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 0; }
        .right { text-align: right; }
        hr { border: 0; border-top: 1px dashed #d1d5db; margin: 8px 0; }
        .stamp { display:inline-block; padding:6px 10px; border:2px solid #059669; color:#059669; border-radius:4px; font-weight:bold; }
    </style>
</head>
<body>
    <div style="text-align:center;">
        <h1>{{ config('app.name', 'CourtMaster') }}</h1>
        <div class="small">OFFICIAL RECEIPT</div>
        <div class="small">No. {{ $payment->receipt_number ?? sprintf('OR-%06d', $payment->id) }}</div>
    </div>

    <hr>

    <table>
        <tr>
            <td>Date</td>
            <td class="right">{{ optional($payment->paid_at ?? $payment->created_at)->format('Y-m-d H:i') }}</td>
        </tr>
        <tr>
            <td>Customer</td>
            <td class="right">{{ $payment->customer?->name ?? 'Walk-in' }}</td>
        </tr>
        <tr>
            <td>Reference</td>
            <td class="right">{{ $payment->gateway_reference ?? '—' }}</td>
        </tr>
        <tr>
            <td>Method</td>
            <td class="right">{{ ucfirst($payment->gateway ?? $payment->method ?? 'manual') }}</td>
        </tr>
    </table>

    <hr>

    <table>
        <tr>
            <td>Description</td>
            <td class="right">{{ class_basename($payment->payable_type) }} #{{ $payment->payable_id }}</td>
        </tr>
        <tr>
            <td><strong>Amount</strong></td>
            <td class="right"><strong>₱{{ number_format($payment->amount, 2) }}</strong></td>
        </tr>
    </table>

    <hr>

    <div style="text-align:center; margin-top:10px;">
        <span class="stamp">PAID</span>
        <div class="small" style="margin-top:6px;">This serves as your official receipt.</div>
    </div>
</body>
</html>
