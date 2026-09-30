<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <title>Compliance pack – {{ $employee->full_name }}</title>
    <style>
        @page { margin: 32px 36px 44px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #101828; line-height: 1.4; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 12.5px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1.5px solid #4F46E5; }
        h3 { font-size: 10.5px; margin: 10px 0 4px; color: #344054; }
        .muted { color: #667085; }
        .cover { border: 1px solid #EAECF0; border-radius: 6px; padding: 12px 14px; margin: 10px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #F9FAFB; color: #344054; padding: 5px 6px; border-bottom: 1px solid #EAECF0; font-weight: bold; }
        td { padding: 5px 6px; border-bottom: 1px solid #EAECF0; vertical-align: top; }
        table.kv td.k { width: 34%; color: #667085; }
        .badge { font-weight: bold; }
        .done { color: #067647; } .check { color: #B54708; } .missing { color: #B42318; } .manual { color: #3538CD; }
        .num { text-align: right; }
        .page-break { page-break-before: always; }
        footer { position: fixed; bottom: -28px; left: 0; right: 0; font-size: 7.5px; color: #667085; }
    </style>
</head>
<body>
    <footer>{{ $business->name }} · Compliance pack for {{ $employee->full_name }} · generated {{ $generated }} by {{ $by }} · Confidential: contains personal data. Not legal advice.</footer>

    <h1>Compliance pack: {{ $employee->full_name }}</h1>
    <div class="muted">{{ $business->name }}@if ($business->licence_number) · sponsor licence {{ $business->licence_number }}@endif</div>
    <div class="cover">
        <strong>{{ $employee->job_title }}</strong> · {{ $employee->isSponsored() ? 'Sponsored worker (Skilled Worker)' : $employee->rtw_basis->label() }}<br>
        Started {{ $employee->start_date->format('j M Y') }}@if ($employee->ended_on) · left {{ $employee->ended_on->format('j M Y') }}@endif<br>
        {{ $summary }} · Generated {{ $generated }} by {{ $by }}
    </div>

    <h2>1. Compliance check</h2>
    <table>
        <thead><tr><th style="width: 34%">Item</th><th style="width: 13%">Status</th><th>Detail</th></tr></thead>
        <tbody>
            @foreach ($check as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="badge {{ $row['status'] }}">{{ $row['badge'] }}</td>
                    <td>{{ $row['detail'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>2. Details</h2>
    @foreach ($details as $section => $fields)
        <h3>{{ $section }}</h3>
        <table class="kv">
            @foreach ($fields as $label => $value)
                <tr><td class="k">{{ $label }}</td><td>{{ $value }}</td></tr>
            @endforeach
        </table>
    @endforeach

    <h2>3. Documents on file</h2>
    <table>
        <thead><tr><th>Category</th><th>File</th><th>Uploaded</th><th>By</th><th>Expires</th></tr></thead>
        <tbody>
            @forelse ($documents as $doc)
                <tr><td>{{ $doc['category'] }}</td><td>{{ $doc['name'] }}</td><td>{{ $doc['uploaded'] }}</td><td>{{ $doc['by'] }}</td><td>{{ $doc['expires'] }}</td></tr>
            @empty
                <tr><td colspan="5" class="muted">No documents on file.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="muted">The documents themselves are kept securely in SponsorSafe and can be shown on request.</p>

    <h2>4. Absence log</h2>
    <table>
        <thead><tr><th>Type</th><th>Dates</th><th class="num">Working days</th><th>Pay</th><th>Reason</th></tr></thead>
        <tbody>
            @forelse ($absences as $a)
                <tr><td>{{ $a['type'] }}</td><td>{{ $a['dates'] }}</td><td class="num">{{ $a['days'] }}</td><td>{{ $a['pay'] }}</td><td>{{ $a['reason'] }}</td></tr>
            @empty
                <tr><td colspan="5" class="muted">No absences recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>5. Home Office reports</h2>
    <table>
        <thead><tr><th>Event</th><th>Triggered</th><th>Deadline</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($tasks as $t)
                <tr><td>{{ $t['event'] }}</td><td>{{ $t['trigger'] }}</td><td>{{ $t['deadline'] }}</td><td>{{ $t['status'] }}@if ($t['done'])<br><span class="muted">{{ $t['done'] }}</span>@endif</td></tr>
            @empty
                <tr><td colspan="4" class="muted">No Home Office reports for this worker.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>6. Change history</h2>
    <table>
        <thead><tr><th>Date</th><th>Change</th><th>From</th><th>To</th><th>By</th></tr></thead>
        <tbody>
            @forelse ($changes as $c)
                <tr><td>{{ $c['date'] }}</td><td>{{ $c['label'] }}</td><td>{{ $c['from'] }}</td><td>{{ $c['to'] }}</td><td>{{ $c['by'] }}</td></tr>
            @empty
                <tr><td colspan="5" class="muted">No changes recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
