<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Connect WhatsApp</title>
    <style>
        :root { --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; --accent: #16a34a; --err: #dc2626; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b1220; --card: #111827; --text: #e5e7eb; --muted: #94a3b8; --border: #1f2937; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding: 16px; }
        .card { width: 100%; max-width: 440px; background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
        h1 { font-size: 20px; margin: 0 0 6px; }
        p { margin: 0 0 16px; color: var(--muted); }
        button { width: 100%; border: 0; border-radius: 10px; padding: 12px 16px; font-size: 15px; font-weight: 600; background: #1877f2; color: #fff; cursor: pointer; }
        button[disabled] { opacity: .6; cursor: default; }
        .status { margin-top: 14px; font-size: 14px; min-height: 21px; }
        .ok { color: var(--accent); } .bad { color: var(--err); }
        ol { color: var(--muted); padding-left: 18px; margin: 0 0 18px; font-size: 14px; }
    </style>
</head>
<body>
<div class="card">
    <h1>Connect your school's WhatsApp</h1>
    @if (! $valid)
        <p class="bad">This connect link has expired or was already used. Close this window and click <b>Connect WhatsApp</b> again in your ERP.</p>
    @elseif ($appId === '' || $configId === '')
        <p class="bad">WhatsApp onboarding is not configured on the server yet. Please contact ERPSaathi support.</p>
    @else
        <p>Sign in with the Facebook account that manages your school's WhatsApp Business account, then choose the business, WhatsApp account and phone number to connect.</p>
        <ol>
            <li>Click the button below.</li>
            <li>Finish every step in the Meta window.</li>
            <li>Keep this window open until it says “Connected”.</li>
        </ol>
        <button id="connect" type="button">Continue with Facebook</button>
        <div id="status" class="status" role="status" aria-live="polite"></div>
    @endif
</div>

@if ($valid && $appId !== '' && $configId !== '')
<script>
(function () {
    var TICKET = @json($ticket);
    var OPENER_ORIGIN = @json($openerOrigin);
    var COMPLETE_URL = @json($completeUrl);
    var sessionInfo = { waba_id: null, phone_number_id: null, event: null };
    var button = document.getElementById('connect');
    var statusEl = document.getElementById('status');

    function setStatus(text, cls) { statusEl.textContent = text; statusEl.className = 'status ' + (cls || ''); }

    function isFacebookOrigin(origin) {
        try { var h = new URL(origin).hostname; return h === 'facebook.com' || h.slice(-13) === '.facebook.com'; } catch (e) { return false; }
    }

    // Session info: which WABA + phone number the admin picked (sessionInfoVersion 3).
    window.addEventListener('message', function (event) {
        if (!isFacebookOrigin(event.origin)) return;
        var data;
        try { data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data; } catch (e) { return; }
        if (!data || data.type !== 'WA_EMBEDDED_SIGNUP') return;
        sessionInfo.event = data.event;
        if (data.data) {
            if (data.data.waba_id) sessionInfo.waba_id = String(data.data.waba_id);
            if (data.data.phone_number_id) sessionInfo.phone_number_id = String(data.data.phone_number_id);
        }
        if (data.event === 'CANCEL') setStatus('Cancelled before finishing. Click the button to try again.', 'bad');
        if (data.event === 'ERROR') setStatus('Meta reported an error: ' + ((data.data && data.data.error_message) || 'unknown'), 'bad');
    });

    function waitForSessionInfo(ms) {
        return new Promise(function (resolve) {
            var start = Date.now();
            (function poll() {
                if (sessionInfo.waba_id || Date.now() - start > ms) return resolve();
                setTimeout(poll, 200);
            })();
        });
    }

    function finish(code) {
        setStatus('Connecting… please keep this window open.');
        waitForSessionInfo(4000).then(function () {
            return fetch(COMPLETE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'omit',
                body: JSON.stringify({ ticket: TICKET, code: code, waba_id: sessionInfo.waba_id, phone_number_id: sessionInfo.phone_number_id })
            });
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, body: body }; });
        }).then(function (r) {
            if (!r.ok) {
                setStatus((r.body && r.body.message) || 'Could not connect. Please try again.', 'bad');
                return;
            }
            setStatus('Connected: ' + (r.body.verified_name || '') + ' ' + (r.body.display_phone_number || '') + '. You can close this window.', 'ok');
            button.disabled = true;
            if (window.opener && OPENER_ORIGIN) {
                window.opener.postMessage({ type: 'erpsaathi-whatsapp-connected' }, OPENER_ORIGIN);
                setTimeout(function () { window.close(); }, 1500);
            }
        }).catch(function () { setStatus('Network error while connecting. Please try again.', 'bad'); });
    }

    window.fbAsyncInit = function () {
        FB.init({ appId: @json($appId), autoLogAppEvents: true, xfbml: false, version: @json($graphVersion) });
        button.disabled = false;
    };
    button.disabled = true;

    button.addEventListener('click', function () {
        setStatus('Waiting for Meta…');
        FB.login(function (response) {
            if (response && response.authResponse && response.authResponse.code) {
                button.disabled = true;
                finish(response.authResponse.code);
            } else if (sessionInfo.event !== 'CANCEL') {
                setStatus('Meta did not complete the sign-in. Click the button to try again.', 'bad');
            }
        }, {
            config_id: @json($configId),
            response_type: 'code',
            override_default_response_type: true,
            extras: { setup: {}, featureType: '', sessionInfoVersion: '3' }
        });
    });
})();
</script>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
@endif
</body>
</html>
