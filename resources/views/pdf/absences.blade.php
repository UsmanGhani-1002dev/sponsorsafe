<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <title>Absence log – {{ $business }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #101828; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .muted { color: #667085; }
        .filters { margin: 8px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #F9FAFB; color: #344054; font-weight: bold; padding: 6px; border-bottom: 1px solid #EAECF0; }
        td { padding: 6px; border-bottom: 1px solid #EAECF0; vertical-align: top; }
        .num { text-align: right; }
        .report { color: #B42318; font-weight: bold; }
        footer { position: fixed; bottom: -12px; left: 0; right: 0; font-size: 8px; color: #667085; }
    </style>
</head>
<body>
    <h1>Absence log – {{ $business }}</h1>
    <div class="muted">Generated {{ $generated }} · {{ count($rows) }} {{ count($rows) === 1 ? 'entry' : 'entries' }}</div>
    @if ($filters)
        <div class="filters">
            @foreach ($filters as $label => $value)
                <strong>{{ $label }}:</strong> {{ $value }}@if (! $loop->last) &nbsp;·&nbsp; @endif
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr><th>Employee</th><th>Type</th><th>Dates</th><th class="num">Days</th><th>Pay</th><th>Reason</th><th>Home Office</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['employee'] }}</td>
                    <td>{{ $r['type'] }}</td>
                    <td>{{ $r['dates'] }}</td>
                    <td class="num">{{ $r['days'] }}</td>
                    <td>{{ $r['pay'] }}</td>
                    <td>{{ $r['reason'] ?? '—' }}</td>
                    <td class="{{ str_starts_with($r['homeOffice']['text'], 'Report') ? 'report' : '' }}">{{ $r['homeOffice']['text'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No absences match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <footer>SponsorSafe · Days are working days (Monday to Friday, excluding England and Wales bank holidays). Not legal advice.</footer>
</body>
</html>
