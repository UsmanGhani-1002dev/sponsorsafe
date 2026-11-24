<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <title>Home Office visit pack – {{ $business->name }}</title>
    <style>
        @include('pdf.partials.pack-styles')
        .green { color: #067647; } .amber { color: #B54708; } .red { color: #B42318; }
        .intro { font-size: 10px; color: #344054; margin: 6px 0 0; }
    </style>
</head>
<body>
    <footer>{{ $business->name }} · Home Office visit pack · generated {{ $generated }} by {{ $by }} · Confidential: contains personal data. Not legal advice.</footer>

    <h1>Home Office visit pack</h1>
    <div class="muted">{{ $business->name }}@if ($business->licence_number) · sponsor licence {{ $business->licence_number }}@endif</div>
    <div class="cover">
        {{ $counts['employees'] }} current {{ $counts['employees'] === 1 ? 'employee' : 'employees' }}, {{ $counts['sponsored'] }} sponsored ·
        {{ $counts['allDone'] }} of {{ $counts['employees'] }} with compliance all done ·
        {{ count($tasks) }} Home Office {{ count($tasks) === 1 ? 'report' : 'reports' }} pending<br>
        Generated {{ $generated }} by {{ $by }}
    </div>
    <p class="intro">This pack holds the records a compliance officer usually asks to see: the sponsor's details and key personnel, work sites,
        every current employee's right-to-work and compliance status, and open Home Office reports, followed by a compliance pack for each employee.
        The documents themselves are kept securely in SponsorSafe and can be shown on request.</p>

    <h2>1. Sponsor details</h2>
    <table class="kv">
        @foreach ($sponsor as $label => $value)
            <tr><td class="k">{{ $label }}</td><td>{!! nl2br(e($value)) !!}</td></tr>
        @endforeach
    </table>

    <h2>2. Key personnel</h2>
    <table>
        <thead><tr><th>Role</th><th>Name</th><th>Email</th><th>Phone</th></tr></thead>
        <tbody>
            @forelse ($people as $p)
                <tr><td>{{ $p['role'] }}</td><td>{{ $p['name'] }}</td><td>{{ $p['email'] }}</td><td>{{ $p['phone'] }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">No key personnel recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>3. Work sites</h2>
    <table>
        <thead><tr><th style="width: 24%">Site</th><th>Address</th><th style="width: 16%">Status</th></tr></thead>
        <tbody>
            @forelse ($sites as $s)
                <tr><td>{{ $s['name'] }}</td><td>{{ $s['address'] }}</td><td>{{ $s['status'] }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">No work sites recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>4. Employees and compliance status</h2>
    <table>
        <thead><tr><th>Employee</th><th>Right to work</th><th>Visa / permission ends</th><th>Checked</th><th>Compliance</th></tr></thead>
        <tbody>
            @forelse ($overview as $o)
                <tr>
                    <td><strong>{{ $o['name'] }}</strong><br><span class="muted">{{ $o['job'] }}</span></td>
                    <td>{{ $o['basis'] }}</td>
                    <td>{{ $o['expiry'] }}</td>
                    <td>{{ $o['checked'] }}</td>
                    <td><span class="badge {{ $o['tone'] }}">{{ $o['status'] }}</span>@if ($o['fix'])<br><span class="muted">To fix: {{ $o['fix'] }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No current employees.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>5. Home Office reports pending</h2>
    <table>
        <thead><tr><th>Event</th><th>Who</th><th>Triggered</th><th>Deadline</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($tasks as $t)
                <tr><td>{{ $t['event'] }}</td><td>{{ $t['who'] }}</td><td>{{ $t['trigger'] }}</td><td>{{ $t['deadline'] }}</td><td>{{ $t['status'] }}</td></tr>
            @empty
                <tr><td colspan="5" class="muted">Nothing pending: every report is up to date.</td></tr>
            @endforelse
        </tbody>
    </table>

    @foreach ($packs as $pack)
        <div class="page-break"></div>
        @include('pdf.partials.worker-pack', $pack)
    @endforeach
</body>
</html>
