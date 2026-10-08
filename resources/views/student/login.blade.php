<!DOCTYPE html>
<html lang="en" class="student-login-light" data-erp-template="school-green">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Student Login</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        html.student-login-dark, html.student-login-dark body { background: #052E16; color-scheme: dark; }
        html.student-login-light, html.student-login-light body { background: #F8FAFC; color-scheme: light; }
        body { margin: 0; min-height: 100vh; }
        #student-login-app { min-height: 100vh; }
    </style>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('student-auth-theme');
                var dark = t === '1';
                document.documentElement.className = dark ? 'student-login-dark' : 'student-login-light';
                document.documentElement.setAttribute('data-erp-template', 'school-green');
                document.addEventListener('DOMContentLoaded', function () {
                    document.body.className = dark ? 'student-login-dark' : 'student-login-light';
                });
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/student-login.js'])
</head>
<body class="student-login-light">
    <div id="student-login-app"></div>
</body>
</html>
