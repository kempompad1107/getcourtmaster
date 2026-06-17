<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111; margin: 20px; }
        h1 { font-size: 20px; color: #059669; margin-bottom: 4px; }
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #f9fafb; text-align: left; padding: 5px 8px; font-size: 10px; color: #6b7280; text-transform: uppercase; }
        td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
        .summary { margin: 8px 0 14px; }
        .stat { display: inline-block; margin-right: 22px; vertical-align: top; }
        .stat-val { font-size: 16px; font-weight: bold; color: #059669; }
        .stat-lbl { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
        .footer { margin-top: 30px; text-align: center; color: #9ca3af; font-size: 9px; }
        .empty { color: #9ca3af; padding: 16px 0; }
    </style>
</head>
<body>
    <h1>{{ $tournament->name }} — {{ $typeLabel }}</h1>
    <p class="meta">
        @if($tournament->venue){{ $tournament->venue }} &nbsp;•&nbsp;@endif
        @if($tournament->starts_at){{ $tournament->starts_at->format('M j, Y') }}@if($tournament->ends_at && !$tournament->ends_at->isSameDay($tournament->starts_at)) – {{ $tournament->ends_at->format('M j, Y') }}@endif &nbsp;•&nbsp;@endif
        Generated: {{ $generatedAt->format('M j, Y g:i A') }}
    </p>

    @if(!empty($report['totals']))
    <div class="summary">
        @foreach($report['totals'] as $label => $value)
            @if(is_array($value))
                @foreach($value as $sub => $subValue)
                <div class="stat"><div class="stat-val">{{ $subValue }}</div><div class="stat-lbl">{{ $label }} — {{ strtoupper($sub) }}</div></div>
                @endforeach
            @else
            <div class="stat"><div class="stat-val">{{ $value }}</div><div class="stat-lbl">{{ $label }}</div></div>
            @endif
        @endforeach
    </div>
    @endif

    @if(empty($report['rows']))
    <p class="empty">No data for this report yet.</p>
    @else
    <table>
        <thead>
            <tr>
                @foreach($report['headings'] as $heading)
                <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($report['rows'] as $row)
            <tr>
                @foreach($report['headings'] as $heading)
                <td>{{ $row[$heading] ?? '' }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <p class="footer">{{ config('app.name') }} — tournament report</p>
</body>
</html>
