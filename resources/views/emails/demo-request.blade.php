<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New demo request</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <div style="max-width:600px;margin:0 auto;padding:20px;">
        <div style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
            <div style="background:linear-gradient(135deg,#16A34A 0%,#166534 100%);color:#ffffff;padding:24px;text-align:center;">
                <h1 style="margin:0;font-size:20px;font-weight:700;">New demo request</h1>
                <p style="margin:6px 0 0;font-size:14px;opacity:0.9;">From the ERPSaathi website</p>
            </div>

            <div style="padding:24px 28px;color:#333333;font-size:15px;line-height:1.6;">
                <p style="margin:0 0 16px;">A school has asked for a demo. Please call them back.</p>

                <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #E5E7EB;background:#F9FAFB;width:35%;">School name</td>
                        <td style="padding:10px 12px;border:1px solid #E5E7EB;font-weight:700;">{{ $details['school_name'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #E5E7EB;background:#F9FAFB;">Phone</td>
                        <td style="padding:10px 12px;border:1px solid #E5E7EB;font-weight:700;">
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $details['phone']) }}" style="color:#15803D;text-decoration:none;">{{ $details['phone'] }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #E5E7EB;background:#F9FAFB;">Submitted</td>
                        <td style="padding:10px 12px;border:1px solid #E5E7EB;">{{ $details['submitted_at'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #E5E7EB;background:#F9FAFB;">Page</td>
                        <td style="padding:10px 12px;border:1px solid #E5E7EB;">{{ $details['page'] }}</td>
                    </tr>
                </table>

                <p style="margin:16px 0 0;font-size:12px;color:#888888;">IP: {{ $details['ip'] ?? '—' }}</p>
            </div>
        </div>
    </div>
</body>
</html>
