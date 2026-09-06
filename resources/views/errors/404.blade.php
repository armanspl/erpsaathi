<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Page not found — erpsaathi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --primary: #4f46e5;
            --primary-deep: #4338ca;
            --primary-soft: #eef2ff;
            --card: #ffffff;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100%;
            font-family: "DM Sans", ui-sans-serif, system-ui, sans-serif;
            background: var(--bg);
            color: var(--ink);
        }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow-x: hidden;
        }
        .bg {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(900px 420px at 12% -10%, rgba(99, 102, 241, 0.18), transparent 60%),
                radial-gradient(700px 380px at 92% 8%, rgba(56, 189, 248, 0.14), transparent 55%),
                linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
        }
        .wrap {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
            text-align: center;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 36px;
            text-decoration: none;
            color: inherit;
        }
        .brand img {
            height: 44px;
            width: auto;
            object-fit: contain;
        }
        .card {
            width: min(520px, 100%);
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 40px 32px 36px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.06);
        }
        .code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            padding: 6px 14px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-deep);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin-bottom: 18px;
        }
        h1 {
            margin: 0 0 12px;
            font-family: Fraunces, Georgia, serif;
            font-size: clamp(1.75rem, 4vw, 2.35rem);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }
        p {
            margin: 0 auto 28px;
            max-width: 36ch;
            color: var(--muted);
            font-size: 1.02rem;
            line-height: 1.55;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform .15s ease, background .15s ease, box-shadow .15s ease;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 10px 24px rgba(79, 70, 229, 0.28);
        }
        .btn-primary:hover {
            background: var(--primary-deep);
            transform: translateY(-1px);
        }
        .btn-ghost {
            background: transparent;
            color: var(--ink);
            border: 1px solid var(--line);
        }
        .btn-ghost:hover {
            background: #f8fafc;
        }
        .hint {
            margin-top: 28px;
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .hint code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.8rem;
            color: var(--primary-deep);
            background: var(--primary-soft);
            padding: 2px 7px;
            border-radius: 6px;
        }
        footer {
            position: relative;
            z-index: 1;
            padding: 20px;
            text-align: center;
            font-size: 0.8rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true"></div>
    <main class="wrap">
        <a class="brand" href="{{ url('/') }}">
            <img src="{{ asset('assets/img/logo/erpsaathi.png') }}" alt="erpsaathi">
        </a>
        <div class="card">
            <div class="code">404</div>
            <h1>Page not found</h1>
            <p>
                {{ $exception->getMessage() && $exception->getMessage() !== 'Not Found'
                    ? $exception->getMessage()
                    : 'This page does not exist, or it is not available on this domain.' }}
            </p>
            <div class="actions">
                <a class="btn btn-primary" href="{{ url('/') }}">Back to home</a>
                <a class="btn btn-ghost" href="javascript:history.back()">Go back</a>
            </div>
            <p class="hint">
                School staff sign in at their subdomain, e.g.
                <code>school.erpsaathi.com/erp/login</code>
            </p>
        </div>
    </main>
    <footer>&copy; {{ date('Y') }} erpsaathi</footer>
</body>
</html>
