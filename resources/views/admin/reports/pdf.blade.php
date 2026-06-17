<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111; margin: 20px; }
        h1 { font-size: 20px; color: #059669; margin-bottom: 4px; }
        h2 { font-size: 13px; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-top: 22px; }
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #f9fafb; text-align: left; padding: 5px 8px; font-size: 10px; color: #6b7280; text-transform: uppercase; }
        td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
        .right { text-align: right; }
        .summary { margin-top: 8px; }
        .stat { display: inline-block; margin-right: 22px; vertical-align: top; }
        .stat-val { font-size: 18px; font-weight: bold; color: #059669; }
        .stat-lbl { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
        .row { margin-top: 4px; }
        .footer { margin-top: 30px; text-align: center; color: #9ca3af; font-size: 9px; }
        .branch { display: inline-block; padding: 2px 6px; background: #f3f4f6; border-radius: 4px; font-size: 9px; color: #4b5563; margin-left: 6px; }
    </style>
</head>
<body>
    <h1>{{ $tenant->name }} — Business Report <span class="branch">{{ $branch_name }}</span></h1>
    <p class="meta">
        Period: {{ \Carbon\Carbon::parse($from)->format('M j, Y') }} to {{ \Carbon\Carbon::parse($to)->format('M j, Y') }}
        &nbsp;•&nbsp; Generated: {{ now()->format('M j, Y g:i A') }}
    </p>

    <h2>Financial Summary</h2>
    <div class="summary">
        <div class="stat"><div class="stat-val">₱{{ number_format($financial['gross_revenue'] ?? 0, 2) }}</div><div class="stat-lbl">Gross Revenue</div></div>
        <div class="stat"><div class="stat-val">₱{{ number_format($financial['net_revenue'] ?? 0, 2) }}</div><div class="stat-lbl">Net Revenue</div></div>
        <div class="stat"><div class="stat-val">₱{{ number_format($financial['discounts'] ?? 0, 2) }}</div><div class="stat-lbl">Discounts</div></div>
        <div class="stat"><div class="stat-val">₱{{ number_format($financial['refunds'] ?? 0, 2) }}</div><div class="stat-lbl">Refunds</div></div>
        <div class="stat"><div class="stat-val">₱{{ number_format($financial['taxes_collected'] ?? 0, 2) }}</div><div class="stat-lbl">Fees / Tax</div></div>
    </div>

    @if(isset($revenue['growth_pct']) && $revenue['growth_pct'] !== null)
        <p class="row">
            Total revenue this period: <strong>₱{{ number_format($revenue['total_revenue'] ?? 0, 2) }}</strong>
            ({{ $revenue['growth_pct'] >= 0 ? '+' : '' }}{{ $revenue['growth_pct'] }}% vs previous: ₱{{ number_format($revenue['previous_total'] ?? 0, 2) }})
        </p>
    @endif

    <h2>Bookings</h2>
    <table>
        <thead><tr><th>Total</th><th>Completed</th><th>Cancelled</th><th>No-show</th><th>Active</th><th>Pending</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $bookings['total'] ?? 0 }}</td>
                <td>{{ $bookings['completed'] ?? 0 }}</td>
                <td>{{ $bookings['cancelled'] ?? 0 }}</td>
                <td>{{ $bookings['no_show'] ?? 0 }}</td>
                <td>{{ $bookings['active'] ?? 0 }}</td>
                <td>{{ $bookings['pending'] ?? 0 }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Court Performance</h2>
    <table>
        <thead><tr>
            <th>Court</th><th>Branch</th>
            <th class="right">Bookings</th><th class="right">Hours</th>
            <th class="right">Revenue</th><th class="right">Utilization</th>
        </tr></thead>
        <tbody>
            @foreach(($courts['rows'] ?? []) as $c)
            <tr>
                <td>{{ $c['court_name'] }}</td>
                <td>{{ $c['branch'] ?? '—' }}</td>
                <td class="right">{{ $c['bookings'] }}</td>
                <td class="right">{{ $c['hours_used'] }}h</td>
                <td class="right">₱{{ number_format($c['revenue'], 2) }}</td>
                <td class="right">{{ $c['utilization_pct'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Court Occupancy</h2>
    <table>
        <thead><tr><th>Court</th><th class="right">Booked Hours</th><th class="right">Total Hours</th><th class="right">Occupancy</th></tr></thead>
        <tbody>
            @foreach($occupancy as $court)
            <tr>
                <td>{{ $court['court_name'] }}</td>
                <td class="right">{{ $court['booked_hours'] }}h</td>
                <td class="right">{{ $court['total_hours'] }}h</td>
                <td class="right">{{ $court['occupancy_rate'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Members</h2>
    <table>
        <thead><tr><th>Total</th><th>Active</th><th>Inactive</th><th>New (period)</th><th>Expiring 30d</th><th>Expired</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $members['total_customers'] ?? 0 }}</td>
                <td>{{ $members['active_customers'] ?? 0 }}</td>
                <td>{{ $members['inactive_customers'] ?? 0 }}</td>
                <td>{{ $members['new_signups'] ?? 0 }}</td>
                <td>{{ $members['expiring_soon'] ?? 0 }}</td>
                <td>{{ $members['expired'] ?? 0 }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Payments</h2>
    <table>
        <thead><tr><th>Method</th><th class="right">Count</th><th class="right">Gross</th><th class="right">Fees</th><th class="right">Net</th></tr></thead>
        <tbody>
            @foreach(($payments['by_method'] ?? []) as $row)
            <tr>
                <td>{{ strtoupper($row['method']) }}</td>
                <td class="right">{{ $row['count'] }}</td>
                <td class="right">₱{{ number_format($row['total'], 2) }}</td>
                <td class="right">₱{{ number_format($row['fees'], 2) }}</td>
                <td class="right">₱{{ number_format($row['total'] - $row['fees'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p class="footer">Report generated by {{ config('app.name', 'CourtMaster') }}</p>
</body>
</html>
