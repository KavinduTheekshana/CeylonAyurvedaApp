<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles for email clients */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        /* Main styles */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #fff;
            width: 100% !important;
            min-width: 100%;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        .header {
            background-color: #28a745;
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
            font-weight: bold;
        }
        
        .header p {
            margin: 0;
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px 20px;
        }
        
        .content h2 {
            margin: 0 0 20px 0;
            color: #333333;
            font-size: 20px;
        }
        
        .content p {
            margin: 0 0 15px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .status-confirmed {
            background-color: #D4EDDA;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #C3E6CB;
            text-align: center;
            margin: 20px 0;
        }
        
        .status-confirmed strong {
            display: block;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .booking-details {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        
        .booking-details h3 {
            margin: 0 0 15px 0;
            color: #333333;
            font-size: 18px;
        }
        
        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .detail-table tr {
            border-bottom: 1px solid #eeeeee;
        }
        
        .detail-table tr:last-child {
            border-bottom: none;
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
        
        .payment-status {
            background-color: #D4EDDA;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 2px solid #28a745;
        }
        
        .payment-status h3 {
            color: #155724;
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        
        .payment-status p {
            color: #155724;
            margin: 0;
            font-weight: bold;
        }
        
        .address-section {
            background-color: #F8F9FA;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #6C757D;
        }
        
        .address-section h3 {
            margin: 0 0 15px 0;
            color: #333333;
            font-size: 18px;
        }
        
        .address-section p {
            margin: 0;
            line-height: 1.5;
        }
        
        .expectations-section {
            background-color: #FFF3CD;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #FFC107;
        }
        
        .expectations-section h3 {
            margin: 0 0 20px 0;
            color: #856404;
            font-size: 18px;
        }
        
        .expectations-list {
            margin: 0;
            padding-left: 20px;
            color: #856404;
        }
        
        .expectations-list li {
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .cta-button {
            text-align: center;
            margin: 30px 0;
        }
        
        .cta-button a {
            background-color: #28a745;
            color: #ffffff;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 6px;
            display: inline-block;
            font-weight: bold;
            font-size: 16px;
        }
        
        .cta-button a:hover {
            background-color: #218838;
        }
        
        .footer {
            background-color: #f8f9fa;
            text-align: center;
            padding: 20px;
            color: #666666;
            font-size: 12px;
            border-top: 1px solid #eeeeee;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        /* Responsive styles */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .content {
                padding: 20px 15px !important;
            }
            
            .header {
                padding: 20px 15px !important;
            }
            
            .header h1 {
                font-size: 20px !important;
            }
            
            .detail-label {
                width: 35% !important;
                font-size: 13px !important;
            }
            
            .detail-value {
                width: 65% !important;
                font-size: 13px !important;
            }
            
            .cta-button a {
                padding: 12px 25px !important;
                font-size: 14px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>✅ Booking Confirmation</h1>
            <p>Your appointment has been successfully confirmed</p>
        </div>

        <div class="content">
            <h2>Hello {{ $booking->name }},</h2>
            
            <p>Thank you for your booking! Your appointment has been confirmed and we look forward to providing you with excellent service.</p>

            <div class="status-confirmed">
                <strong>✅ Booking Status: Confirmed</strong>
                Your appointment is scheduled and confirmed
            </div>

            <div class="booking-details">
                <h3>Booking Details</h3>
                
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">Reference Number:</td>
                        <td class="detail-value">{{ $booking->reference }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Service:</td>
                        <td class="detail-value">{{ $service->title ?? 'N/A' }}</td>
                    </tr>
                    @if($therapist)
                    <tr>
                        <td class="detail-label">Therapist:</td>
                        <td class="detail-value">{{ $therapist->name }}</td>
                    </tr>
                    @else
                    <tr>
                        <td class="detail-label">Therapist:</td>
                        <td class="detail-value">To be assigned</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="detail-label">Date:</td>
                        <td class="detail-value">{{ \Carbon\Carbon::parse($booking->date)->format('l, F j, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Time:</td>
                        <td class="detail-value">{{ $booking->time }}</td>
                    </tr>
                    @if($booking->discount_amount > 0)
                    <tr>
                        <td class="detail-label">Original Price:</td>
                        <td class="detail-value">£{{ number_format($booking->original_price, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Discount Applied:</td>
                        <td class="detail-value">-£{{ number_format($booking->discount_amount, 2) }}</td>
                    </tr>
                    @if($booking->coupon_code)
                    <tr>
                        <td class="detail-label">Coupon Code:</td>
                        <td class="detail-value">{{ $booking->coupon_code }}</td>
                    </tr>
                    @endif
                    @endif
                    <tr>
                        <td class="detail-label">Total Price:</td>
                        <td class="detail-value"><strong>£{{ number_format($booking->price, 2) }}</strong></td>
                    </tr>
                </table>
            </div>

            @if($booking->payment_method === 'card')
            <div class="payment-status">
                <h3>💳 Payment Status</h3>
                <p>✅ Payment Confirmed - Your payment has been processed successfully.</p>
            </div>
            @endif

            <div class="address-section">
                <h3>📍 Appointment Location</h3>
                <p>
                    {{ $booking->address_line1 }}<br>
                    @if($booking->address_line2)
                        {{ $booking->address_line2 }}<br>
                    @endif
                    {{ $booking->city }}, {{ $booking->postcode }}
                </p>
            </div>

            @if($booking->notes)
            <div class="booking-details">
                <h3>📝 Special Notes</h3>
                <p>{{ $booking->notes }}</p>
            </div>
            @endif

            <div class="booking-details">
                <h3>📞 Contact Information</h3>
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">Phone:</td>
                        <td class="detail-value">{{ $booking->phone }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Email:</td>
                        <td class="detail-value">{{ $booking->email }}</td>
                    </tr>
                </table>
            </div>

            <div class="expectations-section">
                <h3>📋 What to Expect</h3>
                <ul class="expectations-list">
                    <li>Your therapist will arrive at the scheduled time</li>
                    <li>Please ensure someone is available to let them in</li>
                    <li>Have a comfortable space prepared for your treatment</li>
                    <li>If you need to make any changes, please contact us as soon as possible</li>
                </ul>
            </div>

            <div class="cta-button">
                <a href="http://ceylonayurvedahealth.co.uk">Visit Our Website</a>
            </div>

            <p>If you have any questions or need to make changes to your appointment, please don't hesitate to contact us.</p>
            
            <p>Thank you for choosing our services!</p>
            
            <p>Best regards,<br>
            <strong>{{ config('app.name') }}</strong></p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{ date('Y') }} Ceylon Ayurveda Health. All rights reserved.</p>
        </div>
    </div>
</body>
</html>