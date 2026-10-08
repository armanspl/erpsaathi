<!DOCTYPE html>
<html lang="en" class="erp-login-light" data-erp-template="school-green">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Forgot Password - School ERP</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        html.erp-login-dark, html.erp-login-dark body { background: #052E16; color-scheme: dark; }
        html.erp-login-light, html.erp-login-light body { background: #F8FAFC; color-scheme: light; }
        body { margin: 0; min-height: 100vh; }
        #erp-forgot-password-app { min-height: 100vh; }
    </style>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('erp-auth-theme');
                var dark = t === 'dark';
                document.documentElement.className = dark ? 'erp-login-dark' : 'erp-login-light';
                document.documentElement.setAttribute('data-erp-template', 'school-green');
                document.addEventListener('DOMContentLoaded', function () {
                    document.body.className = dark ? 'erp-login-dark' : 'erp-login-light';
                });
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/forgot-password.js'])
</head>
<body class="erp-login-light">
    <div id="erp-forgot-password-app"></div>
</body>
</html>
