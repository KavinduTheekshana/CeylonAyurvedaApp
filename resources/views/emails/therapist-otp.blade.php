<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Account</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #9a563a, #a86445);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .otp-code {
            background: #f8f9fa;
            border: 2px solid #9a563a;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .otp-digits {
            font-size: 32px;
            font-weight: bold;
            color: #9a563a;
            letter-spacing: 8px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #9a563a;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌿 Ceylon Ayurveda Health</h1>
            <p>Welcome to our therapist community!</p>
        </div>
        
        <div class="content">
            <h2>Hello {{ $therapist->name }}! 👋</h2>
            
            <p>Thank you for registering as a therapist with Ceylon Ayurveda Health. To complete your account setup, please verify your email address using the code below:</p>
            
            <div class="otp-code">
                <p style="margin: 0; font-weight: 600; color: #9a563a;">Your Verification Code</p>
                <div class="otp-digits">{{ $otp }}</div>
                <p style="margin: 0; font-size: 14px; color: #6c757d;">Enter this code in the app to verify your account</p>
            </div>
            
            <div class="warning">
                <strong>⚠️ Important:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>This code expires in <strong>10 minutes</strong></li>
                    <li>Don't share this code with anyone</li>
                    <li>If you didn't request this, please ignore this email</li>
                </ul>
            </div>
            
            <p>Once verified, you'll be able to:</p>
            <ul>
                <li>✅ Set up your professional profile</li>
                <li>✅ Add your services and availability</li>
                <li>✅ Start accepting patient bookings</li>
                <li>✅ Manage your appointments</li>
            </ul>
            
            <p>If you have any questions or need assistance, our support team is here to help!</p>
            
            <p>Welcome aboard! 🎉</p>
            
            <p>Best regards,<br>
            <strong>Ceylon Ayurveda Health Team</strong></p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} Ceylon Ayurveda Health. All rights reserved.</p>
            <p>This email was sent to {{ $therapist->email }}</p>
        </div>
    </div>
</body>
</html>