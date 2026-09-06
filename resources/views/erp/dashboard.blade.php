<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $erpSchool['browser_title'] ?? $erpSchool['school_name'] ?? 'ERP Dashboard' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (!empty($erpSchool['favicon_url']))
        <link rel="icon" href="{{ $erpSchool['favicon_url'] }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        window.__ERP_USER__ = @json($erpUser);
        window.__ERP_SCHOOL__ = @json($erpSchool ?? []);
        window.__ERP_LOGOUT_URL__ = @json(route('erp.logout'));
        window.__ERP_LOGIN_URL__ = @json(route('erp.login'));
        (function () {
            var saved = localStorage.getItem('erp_dark_mode');
            var tpl = localStorage.getItem('erp_ui_template') || 'midnight-gold';
            var dark = saved === null ? (tpl !== 'ivory-studio' && tpl !== 'skyline-mist') : saved === '1';
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.setAttribute('data-erp-template', tpl);
            document.documentElement.style.background = dark ? '#07080c' : '#f2f4f7';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/erp-app.js'])
    <style>
        html, body { min-height: 100%; background: #07080c; }
        body { margin: 0; font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="antialiased">
    <div id="erp-app"></div>
</body>
</html>
