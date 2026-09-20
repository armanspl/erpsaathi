<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $studentSchool['browser_title'] ?? $studentSchool['school_name'] ?? 'Student Portal' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (!empty($studentSchool['favicon_url']))
        <link rel="icon" href="{{ $studentSchool['favicon_url'] }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        window.__STUDENT__ = @json($studentUser);
        window.__STUDENT_SCHOOL__ = @json($studentSchool ?? []);
        window.__STUDENT_PORTAL_VISIBILITY__ = @json($studentPortalVisibility ?? []);
        window.__STUDENT_LOGOUT_URL__ = @json(route('student.logout'));
        window.__STUDENT_LOGIN_URL__ = @json(route('student.login'));
        (function () {
            var saved = localStorage.getItem('student_dark_mode');
            var dark = saved === null ? true : saved === '1';
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.background = dark ? '#07080c' : '#f2f4f7';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/student-app.js'])
    <style>
        html, body { min-height: 100%; background: #07080c; }
        body { margin: 0; font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="antialiased">
    <div id="student-app"></div>
</body>
</html>
