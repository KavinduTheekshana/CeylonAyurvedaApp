<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Request - Bank Transfer Payment</title>
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
            background-color: #9A563A;
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
        
        .status-pending {
            background-color: #FFF3CD;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #FFEAA7;
            text-align: center;
            margin: 20px 0;
        }
        
        .status-pending strong {
            display: block;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .booking-details {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #9A563A;
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
        
        .bank-details {
            background-color: #E8F4FD;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 2px solid #007cba;
        }
        
        .bank-details h3 {
            color: #005580;
            margin: 0 0 15px 0;
            font-size: 18px;
        }
        
        .bank-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .bank-table tr {
            border-bottom: 1px solid #B8E0FF;
        }
        
        .bank-table tr:last-child {
            border-bottom: none;
        }
        
        .bank-table td {
            padding: 12px 0;
            vertical-align: top;
        }
        
        .bank-label {
            font-weight: bold;
            color: #005580;
            width: 40%;
        }
        
        .bank-value {
            color: #003d5c;
            font-weight: bold;
            width: 60%;
        }
        
        .payment-reference {
            background-color: #FFE4B5;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border: 2px solid #FFA500;
            text-align: center;
        }
        
        .payment-reference .ref-label {
            margin: 0;
            color: #CC6600;
            font-weight: bold;
            font-size: 16px;
        }
        
        .payment-reference .ref-value {
            margin: 5px 0 0 0;
            color: #CC6600;
            font-weight: bold;
            font-size: 20px;
        }
        
        .payment-reference .ref-warning {
            margin: 15px 0 0 0;
            font-size: 14px;
            color: #CC6600;
        }
        
        .therapist-info {
            background-color: #FFF8F0;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border: 1px solid #FFE4B5;
        }
        
        .therapist-info h4 {
            margin: 0 0 10px 0;
            color: #333333;
            font-size: 16px;
        }
        
        .steps-section {
            background-color: #F0F8F0;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        
        .steps-section h3 {
            margin: 0 0 20px 0;
            color: #155724;
            font-size: 18px;
        }
        
        .step-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .step-table td {
            padding: 10px 0;
            vertical-align: top;
        }
        
        .step-number {
            background-color: #28a745;
            color: #ffffff;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            font-size: 14px;
            margin-right: 15px;
        }
        
        .step-text {
            font-size: 14px;
            line-height: 1.5;
        }
        
        .warning-box {
            background-color: #FFF3CD;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #FFA500;
        }
        
        .warning-box strong {
            color: #856404;
            display: block;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .warning-box ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
            color: #856404;
        }
        
        .warning-box li {
            margin-bottom: 8px;
            font-size: 14px;
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
            
            .detail-label,
            .bank-label {
                width: 35% !important;
                font-size: 13px !important;
            }
            
            .detail-value,
            .bank-value {
                width: 65% !important;
                font-size: 13px !important;
            }
            
            .payment-reference .ref-value {
                font-size: 18px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Booking Request - Bank Transfer</h1>
            <p>Complete your payment to confirm your appointment</p>
        </div>

        <div class="content">
            <h2>Hello {{ $booking->name }},</h2>
            
            <p>Thank you for your booking request! We have received your details and are processing your appointment.</p>

            <div class="status-pending">
                <strong>⏳ Booking Status: Pending Payment</strong>
                Your appointment will be confirmed once payment is received
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
                        <td class="detail-value">{{ $service->title }}</td>
                    </tr>
                    @if($therapist)
                    <tr>
                        <td class="detail-label">Therapist:</td>
                        <td class="detail-value">{{ $therapist->name }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="detail-label">Date:</td>
                        <td class="detail-value">{{ \Carbon\Carbon::parse($booking->date)->format('l, F j, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Time:</td>
                        <td class="detail-value">{{ \Carbon\Carbon::parse($booking->time)->format('g:i A') }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Duration:</td>
                        <td class="detail-value">{{ $service->duration }} minutes</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Visit Type:</td>
                        <td class="detail-value">
                            {{ $booking->visit_type === 'home' ? '🏠 Home Visit' : '🏢 Branch Visit' }}
                        </td>
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
                    @if($booking->home_visit_fee && $booking->home_visit_fee > 0)
                    <tr>
                        <td class="detail-label">Home Visit Fee:</td>
                        <td class="detail-value">£{{ number_format($booking->home_visit_fee, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="detail-label">Amount to Pay:</td>
                        <td class="detail-value"><strong>£{{ number_format($booking->price, 2) }}</strong></td>
                    </tr>
                </table>
            </div>

            <div class="bank-details">
                <h3>💳 Bank Transfer Details</h3>
                
                <table class="bank-table">
                    <tr>
                        <td class="bank-label">Bank Name:</td>
                        <td class="bank-value">{{ $bankDetails['bank_name'] }}</td>
                    </tr>
                    <tr>
                        <td class="bank-label">Account Name:</td>
                        <td class="bank-value">{{ $bankDetails['account_name'] }}</td>
                    </tr>
                    <tr>
                        <td class="bank-label">Account Number:</td>
                        <td class="bank-value">{{ $bankDetails['account_number'] }}</td>
                    </tr>
                    <tr>
                        <td class="bank-label">Sort Code:</td>
                        <td class="bank-value">{{ $bankDetails['sort_code'] }}</td>
                    </tr>
                    <tr>
                        <td class="bank-label">Amount:</td>
                        <td class="bank-value">£{{ number_format($booking->price, 2) }}</td>
                    </tr>
                </table>
            </div>

            <div class="payment-reference">
                <p class="ref-label">Payment Reference:</p>
                <p class="ref-value">{{ $bankDetails['reference'] }}</p>
                <p class="ref-warning">⚠️ Please use this exact reference when making your transfer</p>
            </div>

            @if($therapist)
            <div class="therapist-info">
                <h4>Your Therapist</h4>
                <p><strong>{{ $therapist->name }}</strong> will be providing your {{ $service->title }} service.</p>
                @if($therapist->bio)
                    <p>{{ $therapist->bio }}</p>
                @endif
                <p><em>Your therapist will arrive 10 minutes early for your appointment.</em></p>
            </div>
            @else
            <div class="therapist-info">
                <h4>Therapist Assignment</h4>
                <p>Your therapist will be assigned and you'll be notified before your appointment.</p>
            </div>
            @endif

            <div class="booking-details">
                <h3>{{ $booking->visit_type === 'home' ? 'Service Address' : 'Branch Location' }}</h3>
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
                <h3>Special Notes</h3>
                <p>{{ $booking->notes }}</p>
            </div>
            @endif

            <div class="booking-details">
                <h3>Contact Information</h3>
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">Email:</td>
                        <td class="detail-value">{{ $booking->email }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Phone:</td>
                        <td class="detail-value">{{ $booking->phone }}</td>
                    </tr>
                </table>
            </div>

            <div class="steps-section">
                <h3>📋 Next Steps</h3>
                
                <table class="step-table">
                    <tr>
                        <td width="50">
                            <span class="step-number">1</span>
                        </td>
                        <td class="step-text">
                            <strong>Make Payment</strong> - Transfer the amount using the bank details above
                        </td>
                    </tr>
                    <tr>
                        <td width="50">
                            <span class="step-number">2</span>
                        </td>
                        <td class="step-text">
                            <strong>Payment Verification</strong> - We'll verify your payment within 1-2 business days
                        </td>
                    </tr>
                    <tr>
                        <td width="50">
                            <span class="step-number">3</span>
                        </td>
                        <td class="step-text">
                            <strong>Confirmation</strong> - You'll receive a final confirmation once payment is verified
                        </td>
                    </tr>
                    <tr>
                        <td width="50">
                            <span class="step-number">4</span>
                        </td>
                        <td class="step-text">
                            <strong>Appointment</strong> - Your therapist will arrive at the scheduled time
                        </td>
                    </tr>
                </table>
            </div>

            <div class="warning-box">
                <strong>⚠️ Important Notes:</strong>
                <ul>
                    <li>Your appointment is <strong>not confirmed</strong> until payment is received</li>
                    <li>Please make payment within <strong>24 hours</strong> to secure your slot</li>
                    <li>If payment is not received within <strong>48 hours</strong>, your booking may be cancelled</li>
                    <li>We'll send you a confirmation email once payment is verified</li>
                </ul>
            </div>

            <p>If you have any questions or need assistance with the payment process, please don't hesitate to contact us.</p>
            
            <p>Thank you for choosing our services!</p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{ date('Y') }} Ceylon Ayurveda Health. All rights reserved.</p>
        </div>
    </div>
</body>
</html>