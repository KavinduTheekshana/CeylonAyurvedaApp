{{-- resources/views/emails/contact-message-reply.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Response to Your Message</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #9A563A;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #9A563A;
            margin-bottom: 10px;
        }
        .branch-name {
            color: #666;
            font-size: 16px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .original-message {
            background-color: #f8f9fa;
            padding: 20px;
            border-left: 4px solid #9A563A;
            margin: 20px 0;
            border-radius: 5px;
        }
        .original-message h3 {
            margin-top: 0;
            color: #9A563A;
        }
        .admin-response {
            margin: 20px 0;
            padding: 20px;
            background-color: #e8f5e9;
            border-radius: 5px;
            border-left: 4px solid #4caf50;
        }
        .admin-response h3 {
            margin-top: 0;
            color: #2e7d32;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        .contact-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .contact-info h4 {
            margin-top: 0;
            color: #9A563A;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #9A563A;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
        }
        .button:hover {
            background-color: #7d4530;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">{{ config('app.name') }}</div>
            <div class="branch-name">{{ $branchName }}</div>
        </div>

        <div class="greeting">
            <p>Dear {{ $customerName }},</p>
            <p>Thank you for contacting us. We have reviewed your message and are pleased to provide you with the following response.</p>
        </div>

        <div class="original-message">
            <h3>Your Original Message:</h3>
            <p><strong>Subject:</strong> {{ $originalSubject }}</p>
            <p><strong>Message:</strong></p>
            <p>{{ $originalMessage }}</p>
        </div>

        <div class="admin-response">
            <h3>Our Response:</h3>
            <div>{!! $adminResponse !!}</div>
        </div>

        <div class="contact-info">
            <h4>Need Further Assistance?</h4>
            <p>If you have any additional questions or concerns, please don't hesitate to contact us:</p>
            <ul>
                @if($message->branch->email)
                    <li><strong>Email:</strong> {{ $message->branch->email }}</li>
                @endif
                @if($message->branch->phone)
                    <li><strong>Phone:</strong> {{ $message->branch->phone }}</li>
                @endif
                @if($message->branch->address)
                    <li><strong>Address:</strong> {{ $message->branch->address }}, {{ $message->branch->city }}</li>
                @endif
            </ul>
        </div>

        <div class="footer">
            <p>Best regards,<br>
            The {{ $branchName }} Team</p>
            
            <p style="margin-top: 20px; font-size: 12px; color: #999;">
                This email was sent in response to your message submitted on {{ $message->created_at->format('M j, Y \a\t g:i A') }}.
                <br>Message ID: #{{ $message->id }}
            </p>
        </div>
    </div>
</body>
</html>
