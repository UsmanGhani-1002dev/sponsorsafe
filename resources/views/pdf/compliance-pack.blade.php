<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <title>Compliance pack – {{ $employee->full_name }}</title>
    <style>
        @include('pdf.partials.pack-styles')
    </style>
</head>
<body>
    <footer>{{ $business->name }} · Compliance pack for {{ $employee->full_name }} · generated {{ $generated }} by {{ $by }} · Confidential: contains personal data. Not legal advice.</footer>

    @include('pdf.partials.worker-pack')
</body>
</html>
