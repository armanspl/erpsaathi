<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Super Admin — ERPSaathi</title>
    @vite(['resources/css/app.css', 'resources/js/super-admin.js'])
</head>
<body class="bg-slate-50">
    <div id="super-admin-app"></div>
</body>
</html>
