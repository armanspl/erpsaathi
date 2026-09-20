<!DOCTYPE html>
<html lang="en" class="student-login-dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <title>Student Login</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        html.student-login-dark, html.student-login-dark body { background: #07080c; color-scheme: dark; }
        html.student-login-light, html.student-login-light body { background: #f4f1ea; color-scheme: light; }
        body { margin: 0; min-height: 100vh; }
        #student-login-app { min-height: 100vh; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/student-login.js'])
</head>
<body class="student-login-dark">
    <div id="student-login-app"></div>
</body>
</html>
