<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unavailable — erpsaathi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc; --ink: #0f172a; --muted: #64748b; --line: #e2e8f0;
            --primary: #4f46e5; --primary-deep: #4338ca; --primary-soft: #eef2ff; --card: #ffffff;
            --warn: #b45309; --warn-soft: #fffbeb;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; font-family: "DM Sans", system-ui, sans-serif; background: var(--bg); color: var(--ink); }
        body { min-height: 100vh; display: flex; flex-direction: column; }
        .bg {
            position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background:
                radial-gradient(900px 420px at 12% -10%, rgba(99, 102, 241, 0.16), transparent 60%),
                linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
        }
        .wrap { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 20px; text-align: center; }
        .brand { margin-bottom: 36px; } .brand img { height: 44px; }
        .card { width: min(520px, 100%); background: var(--card); border: 1px solid var(--line); border-radius: 24px; padding: 40px 32px; box-shadow: 0 24px 60px rgba(15,23,42,.06); }
        .code { display: inline-flex; padding: 6px 14px; border-radius: 999px; background: var(--warn-soft); color: var(--warn); font-size: 13px; font-weight: 700; letter-spacing: .08em; margin-bottom: 18px; }
        h1 { margin: 0 0 12px; font-family: Fraunces, Georgia, serif; font-size: clamp(1.75rem, 4vw, 2.2rem); font-weight: 700; }
        p { margin: 0 auto 28px; max-width: 38ch; color: var(--muted); line-height: 1.55; }
        .btn { display: inline-flex; align-items: center; padding: 12px 20px; border-radius: 12px; font-weight: 600; text-decoration: none; background: var(--primary); color: #fff; box-shadow: 0 10px 24px rgba(79,70,229,.28); }
        .btn:hover { background: var(--primary-deep); }
        footer { position: relative; z-index: 1; padding: 20px; text-align: center; font-size: .8rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true"></div>
    <main class="wrap">
        <a class="brand" href="{{ url('/') }}"><img src="{{ asset('assets/img/logo/erpsaathi.png') }}" alt="erpsaathi"></a>
        <div class="card">
            <div class="code">503</div>
            <h1>Temporarily unavailable</h1>
            <p>{{ $exception->getMessage() ?: 'This school is currently unavailable. Please try again later.' }}</p>
            <a class="btn" href="{{ url('/') }}">Back to home</a>
        </div>
    </main>
    <footer>&copy; {{ date('Y') }} erpsaathi</footer>
</body>
</html>
