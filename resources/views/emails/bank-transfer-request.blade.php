<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bank Transfer Investment Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .details { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; }
        .amount { font-size: 24px; font-weight: bold; color: #28a745; }
        .reference { font-family: monospace; background: #f8f9fa; padding: 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Investment Request Received</h1>
            <p>Dear {{ $user->name }},</p>
            <p>Thank you for your investment request. Our admin team will contact you within 24 hours with bank transfer details.</p>
        </div>

        <div class="details">
            <h2>Investment Details</h2>
            <p><strong>Reference:</strong> <span class="reference">{{ $investment->reference }}</span></p>
            <p><strong>Location:</strong> {{ $location->name }}, {{ $location->city }}</p>
            <p><strong>Amount:</strong> <span class="amount">£{{ number_format($investment->amount, 2) }}</span></p>
            <p><strong>Payment Method:</strong> Bank Transfer</p>
            <p><strong>Status:</strong> Pending Admin Contact</p>
            @if($investment->notes)
                <p><strong>Notes:</strong> {{ $investment->notes }}</p>
            @endif
        </div>

        <div class="footer">
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>Our admin team will review your request</li>
                <li>You'll receive bank transfer details within 24 hours</li>
                <li>Complete the transfer using the provided details</li>
                <li>Your investment will be confirmed once payment is received</li>
            </ul>
            
            <p>If you have any questions, please don't hesitate to contact us.</p>
            <p>Best regards,<br>The Investment Team</p>
        </div>
    </div>
</body>
</html>