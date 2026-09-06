<!DOCTYPE html>
<html lang="en" class="erp-login-dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <title>ERP Login - Global School</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        html.erp-login-dark, html.erp-login-dark body { background: #07080c; color-scheme: dark; }
        html.erp-login-light, html.erp-login-light body { background: #f4f1ea; color-scheme: light; }
        body { margin: 0; min-height: 100vh; }
        #erp-login-app { min-height: 100vh; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/login.js'])
</head>
<body class="erp-login-dark">
    <div id="erp-login-app"></div>
</body>
</html>