<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — ERPSaathi</title>
    <link rel="icon" href="{{ asset('assets/img/logo/erpsaathi.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            color: #0f172a;
            background: #ffffff;
            line-height: 1.7;
        }
        .wrap { max-width: 760px; margin: 0 auto; padding: 56px 24px 80px; }
        .back { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; color: #4f46e5; text-decoration: none; margin-bottom: 28px; }
        h1 { font-size: 1.9rem; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 6px; }
        .updated { font-size: 0.85rem; color: #94a3b8; margin: 0 0 36px; }
        h2 { font-size: 1.15rem; font-weight: 700; margin: 32px 0 10px; }
        p, li { font-size: 0.95rem; color: #334155; }
        ul { padding-left: 20px; }
    </style>
</head>
<body>
    <div class="wrap">
        <a class="back" href="{{ url('/') }}">&larr; Back to ERPSaathi</a>
        <h1>{{ $title }}</h1>
        <p class="updated">Last updated {{ $updated }}</p>
        {{ $slot }}
    </div>
</body>
</html>
