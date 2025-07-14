<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bank Transfer Investment Confirmed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d4edda; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .details { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; }
        .amount { font-size: 24px; font-weight: bold; color: #28a745; }
        .reference { font-family: monospace; background: #f8f9fa; padding: 5px; border-radius: 3px; }
        .success { color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="success">✅ Investment Confirmed!</h1>
            <p>Dear {{ $user->name }},</p>
            <p>Great news! Your bank transfer has been received and your investment has been confirmed.</p>
        </div>

        <div class="details">
            <h2>Investment Details</h2>
            <p><strong>Reference:</strong> <span class="reference">{{ $investment->reference }}</span></p>
            <p><strong>Location:</strong> {{ $location->name }}, {{ $location->city }}</p>
            <p><strong>Amount:</strong> <span class="amount">£{{ number_format($investment->amount, 2) }}</span></p>
            <p><strong>Payment Method:</strong> Bank Transfer</p>
            <p><strong>Status:</strong> <span class="success">Confirmed</span></p>
            <p><strong>Confirmed Date:</strong> {{ $investment->bank_transfer_confirmed_at->format('F j, Y \a\t g:i A') }}</p>
            @if($investment->notes)
                <p><strong>Notes:</strong> {{ $investment->notes }}</p>
            @endif
        </div>

        <div class="footer">
            <p><strong>What's Next:</strong></p>
            <ul>
                <li>Your investment is now active and contributing to the location's funding</li>
                <li>You'll receive regular updates on the investment progress</li>
                <li>You can view your investment details in your account dashboard</li>
            </ul>
            
            <p>Thank you for your investment and trust in our platform!</p>
            <p>Best regards,<br>The Investment Team</p>
        </div>
    </div>
</body>
</html>