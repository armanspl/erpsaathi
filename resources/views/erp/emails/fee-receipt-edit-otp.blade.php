<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve receipt edit</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <div style="max-width:600px;margin:0 auto;padding:20px;">
        <div style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
            <div style="background:linear-gradient(135deg,#4F46E5 0%,#7C3AED 100%);color:#ffffff;padding:24px;text-align:center;">
                <h1 style="margin:0;font-size:20px;font-weight:700;">{{ $details['school'] }}</h1>
                <p style="margin:6px 0 0;font-size:14px;opacity:0.9;">Fee receipt edit — approval code</p>
            </div>

            <div style="padding:24px 28px;color:#333333;font-size:15px;line-height:1.6;">
                <p style="margin:0 0 16px;"><strong>{{ $details['editor'] }}</strong> wants to change receipt <strong>{{ $details['receipt_no'] }}</strong> of <strong>{{ $details['student'] }}</strong>@if ($details['admission_no'] !== '') (Adm. No. {{ $details['admission_no'] }})@endif.</p>

                <div style="text-align:center;margin:20px 0;">
                    <div style="display:inline-block;letter-spacing:10px;font-size:32px;font-weight:700;color:#4F46E5;background:#EEF2FF;border-radius:10px;padding:14px 24px 14px 34px;">{{ $otp }}</div>
                    <p style="margin:10px 0 0;font-size:13px;color:#666666;">Give this code to the person editing only if you approve the change. It expires in {{ $details['minutes'] }} minutes.</p>
                </div>

                <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">
                    <tr>
                        <td style="padding:8px 10px;border:1px solid #E5E7EB;background:#F9FAFB;width:40%;">Total before</td>
                        <td style="padding:8px 10px;border:1px solid #E5E7EB;">₹{{ number_format($details['before_total'], 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 10px;border:1px solid #E5E7EB;background:#F9FAFB;">Total after</td>
                        <td style="padding:8px 10px;border:1px solid #E5E7EB;font-weight:700;">₹{{ number_format($details['after_total'], 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 10px;border:1px solid #E5E7EB;background:#F9FAFB;">Reason</td>
                        <td style="padding:8px 10px;border:1px solid #E5E7EB;">{{ $details['reason'] }}</td>
                    </tr>
                </table>

                @if ($details['changes'] !== [])
                    <p style="margin:16px 0 6px;font-weight:600;">What changes</p>
                    <ul style="margin:0;padding-left:20px;font-size:14px;">
                        @foreach ($details['changes'] as $change)
                            <li style="margin:2px 0;">{{ $change }}</li>
                        @endforeach
                    </ul>
                @endif

                <p style="margin:20px 0 0;font-size:13px;color:#dc3545;">If you did not expect this, do not share the code — the receipt stays unchanged without it.</p>
            </div>

            <div style="background:#f8f9fa;padding:16px;text-align:center;color:#666666;font-size:12px;">
                &copy; {{ date('Y') }} {{ $details['school'] }}
            </div>
        </div>
    </div>
</body>
</html>
