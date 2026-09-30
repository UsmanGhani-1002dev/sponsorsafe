<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Only the public website may be indexed; the app, portal and super admin never. --}}
    @if (str_starts_with($page['component'] ?? '', 'Website/') && app()->isProduction())
        <meta name="description" content="UKVI compliance for small UK sponsor licence holders: right-to-work checks, absence rules, Home Office reporting deadlines and documents in one place.">
    @else
        <meta name="robots" content="noindex">
    @endif
    <title inertia>{{ config('app.name', 'SponsorSafe') }}</title>
    {{-- Apply the saved (or system) theme before first paint, so dark mode never flashes white. --}}
    <script>try{var t=localStorage.getItem('theme');if(t==='dark'||(!t&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')}catch(e){}</script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
