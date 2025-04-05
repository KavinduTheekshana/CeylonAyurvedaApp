<!-- resources/views/emails/verification.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #eee;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .verification-code {
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 4px;
            margin: 30px 0;
            color: #9A563A;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Email Verification</h2>
        </div>

        <p>Hello {{ $name }},</p>

        <p>Thank you for registering with Ceylon Ayurveda Health. To complete your registration, please enter the following verification code in the app:</p>

        <div class="verification-code">{{ $code }}</div>

        <p>This code will expire in 10 minutes. If you did not request this verification, please ignore this email.</p>

        <p>Best regards,<br>Ceylon Ayurveda Health Team</p>

        <div class="footer">
            <p>This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>