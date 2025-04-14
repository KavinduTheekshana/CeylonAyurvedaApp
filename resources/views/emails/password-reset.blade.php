<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
        }
        .header {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .content {
            padding: 20px 0;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 5px;
            color: #9A563A;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Password Reset Code</h2>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>You have requested to reset your password. Please use the following code to complete the reset process:</p>
            <div class="code">{{ $otp }}</div>
            <p>This code will expire in 60 minutes. If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
            <p>Thank you,</p>
            <p>Your App Team</p>
        </div>
        <div class="footer">
            <p>This is an automated email, please do not reply.</p>
        </div>
    </div>
</body>
</html>