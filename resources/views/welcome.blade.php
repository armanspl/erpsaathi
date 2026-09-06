<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">

    <title>{{ $welcome['schoolName'] }} — Complete School ERP &amp; Management System</title>
    <meta name="description" content="{{ $welcome['schoolName'] }} School ERP — one secure, centralized platform for academics, student management, attendance, fees, examinations, finance &amp; payroll, library, transport, inventory and reports.">
    <link rel="canonical" href="{{ url('/') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $welcome['schoolName'] }} — Complete School ERP">
    <meta property="og:description" content="One secure, centralized platform for academics, attendance, fees, examinations, finance, transport and reporting.">
    <meta property="og:url" content="{{ url('/') }}">
    @if (!empty($welcome['logoUrl']))
        <meta property="og:image" content="{{ $welcome['logoUrl'] }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (!empty($welcome['logoUrl']))
        <link rel="icon" href="{{ $welcome['logoUrl'] }}">
    @endif

    <script>
        window.__WELCOME__ = {!! \Illuminate\Support\Js::from($welcome) !!};
    </script>

    <script>
        // Pre-paint the correct background so a saved light theme doesn't flash dark.
        (function () {
            try {
                var t = localStorage.getItem('erp-welcome-theme');
                var bg = t === 'light' ? '#f8fafc' : '#07080c';
                document.documentElement.style.backgroundColor = bg;
                document.addEventListener('DOMContentLoaded', function () { document.body.style.backgroundColor = bg; });
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/welcome.js'])

    <style>
        html, body { margin: 0; min-height: 100vh; background: #07080c; }
        #erp-welcome-app { min-height: 100vh; }
    </style>
</head>
<body>
    <div id="erp-welcome-app"></div>
</body>
</html>
