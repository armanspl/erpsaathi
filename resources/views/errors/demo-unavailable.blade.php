<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo unavailable — erpsaathi</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: grid; place-items: center; margin: 0; }
        .card { max-width: 420px; padding: 2rem; border-radius: 1rem; background: #1e293b; text-align: center; }
        a { color: #a5b4fc; }
        .detail { margin-top: 1rem; font-size: 12px; color: #94a3b8; word-break: break-word; }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="font-size:1.25rem;margin:0 0 .75rem">Demo unavailable</h1>
        <p style="margin:0;color:#94a3b8">{{ $message }}</p>
        @if (!empty($detail))
            <p class="detail">{{ $detail }}</p>
        @endif
        <p style="margin:1.5rem 0 0"><a href="/">← Back to homepage</a></p>
    </div>
</body>
</html>
