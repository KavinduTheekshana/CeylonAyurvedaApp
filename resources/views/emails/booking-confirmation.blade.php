<!DOCTYPE html>
<html>
<head>
    <title>Booking Confirmation</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #9A563A;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
        }
        .booking-details {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 0.8em;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Booking Confirmation</h1>
        </div>
        
        <div class="content">
            <p>Dear {{ $booking->name }},</p>
            
            <p>Thank you for your booking. Your booking has been confirmed and we look forward to providing our services to you.</p>
            
            <div class="booking-details">
                <h2>Booking Details</h2>
                <p><strong>Reference:</strong> {{ $booking->reference }}</p>
                <p><strong>Service:</strong> {{ $service->title }}</p>
                <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($booking->date)->format('l, F j, Y') }}</p>
                <p><strong>Time:</strong> {{ $booking->time }}</p>
                <p><strong>Address:</strong><br>
                    {{ $booking->address_line1 }}<br>
                    @if($booking->address_line2){{ $booking->address_line2 }}<br>@endif
                    {{ $booking->city }}<br>
                    {{ $booking->postcode }}
                </p>
                @if($booking->notes)
                <p><strong>Additional Notes:</strong> {{ $booking->notes }}</p>
                @endif
                <p><strong>Total Price:</strong> £{{ number_format($booking->price, 2) }}</p>
            </div>
            
            <p>If you need to make any changes to your booking or have any questions, please contact us as soon as possible at <a href="mailto:support@ceylonayurvedahealth.com">support@ceylonayurvedahealth.com</a> or call us at +44 20 313 78 313.</p>
            
            <p>Thank you for choosing our services.</p>
            
            <p>Best regards,<br>
            Ceylon Ayurveda Health</p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} Ceylon Ayurveda Health. All rights reserved.</p>
            <p>464 Alexandra Ave, Rayners Lane, Harrow HA2 9TL</p>
        </div>
    </div>
</body>
</html>