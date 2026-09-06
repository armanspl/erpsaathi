<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Global School ERP</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .email-wrapper {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .email-header {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .email-header img {
            width: 72px;
            height: 72px;
            border-radius: 14px;
            object-fit: contain;
            background: #fff;
            padding: 8px;
            margin-bottom: 10px;
        }
        .email-header h1 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
        }
        .email-body {
            padding: 30px;
        }
        .email-body p {
            color: #333333;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 15px;
        }
        .reset-button {
            display: inline-block;
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            color: white !important;
            text-decoration: none;
            padding: 15px 36px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            margin: 20px 0;
            box-shadow: 0 8px 24px rgba(79,70,229,0.35);
            transition: transform 0.15s ease;
        }
        .reset-button:hover {
            transform: translateY(-2px);
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666666;
            font-size: 0.9rem;
        }
        .warning-text {
            color: #dc3545;
            font-size: 0.9rem;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="email-wrapper">
            <div class="email-header">
                <img src="{{ asset('assets/img/logo/school-logo.png') }}" alt="School Logo">
                <h1>Global School ERP</h1>
            </div>

            <div class="email-body">
                <p>Hello <strong>{{ $user->name }}</strong>,</p>

                <p>We received a request to reset your ERP account password. Click the button below to create a new password:</p>

                <div style="text-align: center;">
                    <a href="{{ $resetUrl }}" class="reset-button">Reset Password</a>
                </div>

                <p class="warning-text">
                    <strong>Note:</strong> This password reset link will expire in 2 hours. If you did not request a password reset, please ignore this email or contact the administrator.
                </p>

                <p style="margin-top: 30px;">
                    If the button above doesn't work, copy and paste the following link into your browser:<br>
                    <a href="{{ $resetUrl }}" style="color: #4F46E5;">{{ $resetUrl }}</a>
                </p>
            </div>

            <div class="email-footer">
                <p>&copy; {{ date('Y') }} Global School. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>