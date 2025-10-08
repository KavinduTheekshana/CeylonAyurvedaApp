<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Cancelled</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #DC2626;
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px 20px;
        }
        .booking-details {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #DC2626;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }
        .detail-table td {
            padding: 10px 0;
            vertical-align: top;
        }
        .detail-label {
            font-weight: bold;
            color: #666666;
            width: 40%;
        }
        .detail-value {
            color: #333333;
            width: 60%;
        }
        .info-box {
            background-color: #FEF3C7;
            border-left: 4px solid #F59E0B;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            background-color: #f8f9fa;
            text-align: center;
            padding: 20px;
            color: #666666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Booking Cancelled</h1>
        </div>

        <div class="content">
            <p>Hello {{ $booking->name }},</p>
            
            <p>Your booking has been successfully cancelled.</p>

            <div class="booking-details">
                <h3>Cancelled Booking Details</h3>
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">Booking Reference:</td>
                        <td class="detail-value"><strong>{{ $booking->reference }}</strong></td>
                    </tr>
                    <tr>
                        <td class="detail-label">Service:</td>
                        <td class="detail-value">{{ $service->title ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Date:</td>
                        <td class="detail-value">{{ \Carbon\Carbon::parse($booking->date)->format('l, F j, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Time:</td>
                        <td class="detail-value">{{ \Carbon\Carbon::parse($booking->time)->format('g:i A') }}</td>
                    </tr>
                    @if($therapist)
                    <tr>
                        <td class="detail-label">Therapist:</td>
                        <td class="detail-value">{{ $therapist->name }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="detail-label">Amount:</td>
                        <td class="detail-value">£{{ number_format($booking->price, 2) }}</td>
                    </tr>
                </table>
            </div>

            @if($booking->payment_method === 'card' && $booking->payment_status === 'paid')
            <div class="info-box">
                <strong>Refund Information:</strong>
                <p>If you paid by card, your refund will be processed within 5-7 business days to your original payment method.</p>
            </div>
            @endif

            <p>If you have any questions about this cancellation, please don't hesitate to contact us.</p>

            <p>We hope to serve you again soon!</p>

            <p>Best regards,<br>
            <strong>Ceylon Ayurveda Health Team</strong></p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply directly to this message.</p>
            <p>&copy; {{ date('Y') }} Ceylon Ayurveda Health. All rights reserved.</p>
        </div>
    </div>
</body>
</html>