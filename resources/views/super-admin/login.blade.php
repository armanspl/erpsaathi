<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Super Admin Login — ERPSaathi</title>
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: Manrope, system-ui, sans-serif; background: #f8fafc; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { width: 100%; max-width: 420px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 32px; box-shadow: 0 12px 40px rgba(15,23,42,.08); }
        h1 { margin: 0 0 6px; font-size: 24px; color: #0f172a; }
        p { margin: 0 0 24px; color: #64748b; font-size: 14px; }
        label { display: block; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
        input[type=email], input[type=password] { width: 100%; box-sizing: border-box; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
        button { width: 100%; padding: 12px; border: 0; border-radius: 10px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; font-weight: 700; cursor: pointer; }
        .error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .logo { height: 48px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ asset('assets/img/logo/erpsaathi.png') }}" alt="ERPSaathi" class="logo">
        <h1>Super Admin</h1>
        <p>Sign in to manage schools and tenants.</p>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ route('super-admin.login.submit') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>
            <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-weight:500;margin-bottom:18px;">
                <input type="checkbox" name="remember" value="1"> Remember me
            </label>
            <button type="submit">Sign in</button>
        </form>
    </div>
</body>
</html>
