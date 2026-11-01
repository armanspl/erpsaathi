<!DOCTYPE html>
<html lang="en" data-erp-template="school-green">
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        window.__STUDENT__ = @json($studentUser);
        window.__STUDENT_SCHOOL__ = @json($studentSchool ?? []);
        window.__STUDENT_PORTAL_VISIBILITY__ = @json($studentPortalVisibility ?? []);
        window.__STUDENT_LOGOUT_URL__ = @json(route('student.logout'));
        window.__STUDENT_LOGIN_URL__ = @json(route('student.login'));
        (function () {
            var saved = localStorage.getItem('student_dark_mode');
            var dark = saved === '1'; // School Green: light unless the student chose night mode
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.setAttribute('data-erp-template', 'school-green');
            document.documentElement.style.background = dark ? '#052E16' : '#F8FAFC';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/student-app.js'])
    <style>
        html, body { min-height: 100%; background: #F8FAFC; }
        body { margin: 0; font-family: 'Inter', 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="antialiased">
    <div id="student-app"></div>
</body>
</html>
