<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Bank Transfer Investment Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #fff3cd; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #ffeaa7; }
        .details { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; }
        .amount { font-size: 24px; font-weight: bold; color: #dc3545; }
        .reference { font-family: monospace; background: #f8f9fa; padding: 5px; border-radius: 3px; }
        .urgent { color: #856404; }
        .action-required { background: #f8d7da; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="urgent">🚨 New Bank Transfer Investment Request</h1>
            <p>A new investment request requiring bank transfer has been submitted and needs admin attention.</p>
        </div>

        <div class="action-required">
            <h2>⚡ Action Required</h2>
            <p>Please contact the investor within 24 hours with bank transfer details.</p>
        </div>

        <div class="details">
            <h2>Investment Details</h2>
            <p><strong>Reference:</strong> <span class="reference">{{ $investment->reference }}</span></p>
            <p><strong>Investment ID:</strong> {{ $investment->id }}</p>
            <p><strong>Investor:</strong> {{ $user->name }} ({{ $user->email }})</p>
            <p><strong>Location:</strong> {{ $location->name }}, {{ $location->city }}</p>
            <p><strong>Amount:</strong> <span class="amount">£{{ number_format($investment->amount, 2) }}</span></p>
            <p><strong>Payment Method:</strong> Bank Transfer</p>
            <p><strong>Request Date:</strong> {{ $investment->created_at->format('F j, Y \a\t g:i A') }}</p>
            @if($investment->notes)
                <p><strong>Investor Notes:</strong> {{ $investment->notes }}</p>
            @endif
        </div>

        <div class="details">
            <h2>Investor Contact Information</h2>
            <p><strong>Name:</strong> {{ $user->name }}</p>
            <p><strong>Email:</strong> {{ $user->email }}</p>
            @if($user->phone)
                <p><strong>Phone:</strong> {{ $user->phone }}</p>
            @endif
        </div>

        <div class="footer">
            <p><strong>Required Actions:</strong></p>
            <ol>
                <li>Contact the investor within 24 hours</li>
                <li>Provide bank transfer details</li>
                <li>Monitor for payment receipt</li>
                <li>Confirm the investment once payment is received</li>
            </ol>
            
            <p><strong>Admin Panel:</strong> <a href="{{ config('app.url') }}/admin/investments/{{ $investment->id }}">View Investment</a></p>
            
            <p>This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>