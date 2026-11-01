<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Super Admin Login — ERPSaathi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif; -webkit-font-smoothing: antialiased; margin: 0; min-height: 100vh; overflow-x: hidden;
            display: flex; align-items: center; justify-content: center; padding: 16px;
            background: radial-gradient(1200px 700px at 20% 10%, #134e4a 0%, transparent 60%),
                        radial-gradient(900px 600px at 90% 90%, #312e81 0%, transparent 55%), #0b1220;
            color: #0f172a;
        }
        /* Receding 3D floor grid */
        .floor {
            position: fixed; left: -50%; right: -50%; bottom: -40%; height: 90%;
            background-image: linear-gradient(rgba(94,234,212,.22) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(94,234,212,.22) 1px, transparent 1px);
            background-size: 60px 60px; transform: perspective(600px) rotateX(70deg);
            -webkit-mask-image: linear-gradient(to top, #000 10%, transparent 80%);
                    mask-image: linear-gradient(to top, #000 10%, transparent 80%);
            animation: floor 6s linear infinite; pointer-events: none;
        }
        @keyframes floor { to { background-position: 0 60px, 0 0; } }

        /* Floating 3D cubes */
        .cube { position: fixed; width: var(--s); height: var(--s); transform-style: preserve-3d; animation: spin var(--d) linear infinite; pointer-events: none; }
        .cube i { position: absolute; inset: 0; border: 1px solid rgba(255,255,255,.35); background: var(--c); }
        .cube i:nth-child(1) { transform: translateZ(calc(var(--s) / 2)); }
        .cube i:nth-child(2) { transform: rotateY(180deg) translateZ(calc(var(--s) / 2)); }
        .cube i:nth-child(3) { transform: rotateY(90deg) translateZ(calc(var(--s) / 2)); }
        .cube i:nth-child(4) { transform: rotateY(-90deg) translateZ(calc(var(--s) / 2)); }
        .cube i:nth-child(5) { transform: rotateX(90deg) translateZ(calc(var(--s) / 2)); }
        .cube i:nth-child(6) { transform: rotateX(-90deg) translateZ(calc(var(--s) / 2)); }
        @keyframes spin { from { transform: rotateX(0) rotateY(0); } to { transform: rotateX(360deg) rotateY(360deg); } }

        .stage { perspective: 1100px; width: 100%; max-width: 420px; position: relative; z-index: 2; }
        .card {
            position: relative; background: linear-gradient(160deg, rgba(255,255,255,.98), rgba(241,245,249,.96));
            border-radius: 22px; padding: 34px 32px 30px; transform-style: preserve-3d;
            transform: rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg));
            transition: transform .25s ease-out;
            box-shadow: 0 1px 0 rgba(255,255,255,.8) inset, 0 -6px 0 #0f766e inset,
                        0 30px 60px -12px rgba(0,0,0,.6), 0 18px 36px -18px rgba(13,148,136,.6);
        }
        .card::before { /* thick slab edge under the card */
            content: ''; position: absolute; inset: 10px 6px -14px; border-radius: 22px; z-index: -1;
            background: linear-gradient(180deg, #0f766e, #134e4a); transform: translateZ(-30px);
            filter: blur(.3px);
        }
        .lift { transform: translateZ(40px); }
        .logo-tile {
            width: 64px; height: 64px; border-radius: 18px; display: grid; place-items: center; margin-bottom: 18px;
            background: #fff; transform: translateZ(60px);
            box-shadow: 0 10px 0 -2px #ccfbf1, 0 18px 30px -8px rgba(15,118,110,.55);
        }
        .logo-tile img { height: 40px; width: auto; }
        h1 { margin: 0 0 6px; font-size: 26px; font-weight: 800; letter-spacing: -.02em; }
        p.sub { margin: 0 0 24px; color: #64748b; font-size: 14px; }
        label { display: block; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
        input[type=email], input[type=password] {
            width: 100%; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; font-size: 14px;
            background: #fff; box-shadow: inset 0 2px 4px rgba(15,23,42,.06); outline: none; transition: box-shadow .15s, border-color .15s;
        }
        input[type=email]:focus, input[type=password]:focus { border-color: #14b8a6; box-shadow: inset 0 2px 4px rgba(15,23,42,.06), 0 0 0 4px rgba(20,184,166,.15); }
        .remember { display: flex; align-items: center; gap: 8px; text-transform: none; letter-spacing: 0; font-weight: 500; margin-bottom: 20px; }
        button {
            width: 100%; padding: 13px; border: 0; border-radius: 12px; cursor: pointer; font: inherit; font-weight: 800; font-size: 15px;
            color: #fff; background: linear-gradient(180deg, #14b8a6, #0f766e);
            box-shadow: 0 6px 0 #115e59, 0 14px 24px -8px rgba(15,118,110,.7); transform: translateY(0);
            transition: transform .08s, box-shadow .08s;
        }
        button:hover { filter: brightness(1.05); }
        button:active { transform: translateY(5px); box-shadow: 0 1px 0 #115e59, 0 6px 12px -6px rgba(15,118,110,.7); }
        .error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px 12px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
        @media (prefers-reduced-motion: reduce) { .floor, .cube { animation: none; } .card { transition: none; } }
        @media (max-width: 640px) { .cube { display: none; } .card { padding: 28px 22px 24px; } }
    </style>
</head>
<body>
    <div class="floor"></div>
    <div class="cube" style="--s:70px;--d:18s;--c:rgba(20,184,166,.18);top:12%;left:10%"><i></i><i></i><i></i><i></i><i></i><i></i></div>
    <div class="cube" style="--s:46px;--d:14s;--c:rgba(129,140,248,.22);top:68%;left:16%"><i></i><i></i><i></i><i></i><i></i><i></i></div>
    <div class="cube" style="--s:90px;--d:24s;--c:rgba(94,234,212,.12);top:18%;right:10%"><i></i><i></i><i></i><i></i><i></i><i></i></div>
    <div class="cube" style="--s:38px;--d:11s;--c:rgba(244,114,182,.2);bottom:16%;right:18%"><i></i><i></i><i></i><i></i><i></i><i></i></div>

    <div class="stage">
        <div class="card" id="card">
            <div class="logo-tile"><img src="{{ asset('assets/img/logo/erpsaathi.png') }}" alt="ERPSaathi"></div>
            <div class="lift">
                <h1>Super Admin</h1>
                <p class="sub">Sign in to manage schools and tenants.</p>
                @if ($errors->any())
                    <div class="error">{{ $errors->first() }}</div>
                @endif
                <form method="post" action="{{ route('super-admin.login.submit') }}">
                    @csrf
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required>
                    <label class="remember"><input type="checkbox" name="remember" value="1"> Remember me</label>
                    <button type="submit">Sign in</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        // Tilt the card toward the pointer (skipped for reduced motion / touch).
        (function () {
            var card = document.getElementById('card');
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !window.matchMedia('(hover: hover)').matches) return;
            document.addEventListener('mousemove', function (e) {
                var x = e.clientX / window.innerWidth - 0.5, y = e.clientY / window.innerHeight - 0.5;
                card.style.setProperty('--ry', (x * 14).toFixed(2) + 'deg');
                card.style.setProperty('--rx', (-y * 12).toFixed(2) + 'deg');
            });
            document.addEventListener('mouseleave', function () {
                card.style.setProperty('--ry', '0deg');
                card.style.setProperty('--rx', '0deg');
            });
        })();
    </script>
</body>
</html>
