<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Ceylon Ayurveda Health - Investment Request Received</title>
    <style>
        :root {
            color-scheme: light dark;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .container {
            max-width: 650px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 0;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .pending-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            border: 3px solid rgba(255,255,255,0.2);
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.5px;
            color: white;
        }
        
        .header .subtitle {
            margin: 8px 0 0;
            font-size: 16px;
            opacity: 0.9;
            font-weight: 300;
            color: white;
        }
        
        .content {
            padding: 40px 30px;
            background-color: #ffffff;
            color: #333;
        }
        
        .welcome-message {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .section {
            margin: 40px 0;
        }
        
        .section-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #3498db;
        }
        
        .section h2 {
            margin: 0;
            font-size: 24px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .investment-details {
            padding: 30px;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        
        /* Dark mode - multiple selectors for better compatibility */
        @media (prefers-color-scheme: dark) {
            .investment-details {
                border-color: #5d5d5d !important;
                box-shadow: 0 2px 10px rgba(0,0,0,0.3) !important;
            }
        }
        
        /* Force dark mode for various email clients */
        [data-ogsc] .investment-details,
        [data-ogsb] .investment-details,
        .dark-mode .investment-details,
        [data-dark-mode] .investment-details {
            border-color: #5d5d5d !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3) !important;
        }
        
        .detail-row {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .detail-label {
            display: table-cell;
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }
        
        .detail-value {
            display: table-cell;
            color: #333;
            font-size: 15px;
            text-align: right;
            width: 50%;
            vertical-align: top;
        }
        
        .amount {
            font-size: 28px;
            font-weight: 700;
            color: #27ae60;
            text-shadow: 0 1px 2px rgba(39, 174, 96, 0.2);
        }
        
        .reference {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .next-steps {
            padding: 25px;
            border-radius: 12px;
            margin: 30px 0;
            border-left: 4px solid #27ae60;
        }
        
        .next-steps h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
        }
        
        .next-steps ul {
            margin: 0;
            padding-left: 20px;
            color: #2c3e50;
        }
        
        .next-steps li {
            margin-bottom: 8px;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .next-steps li:last-child {
            margin-bottom: 0;
        }
        
        .pending-highlight {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin: 30px 0;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
        }
        
        .pending-highlight h3 {
            margin: 0 0 10px 0;
            font-size: 20px;
            font-weight: 600;
            color: white;
        }
        
        .pending-highlight p {
            margin: 0;
            font-size: 16px;
            opacity: 0.9;
            color: white;
        }
        
        .footer {
            background-color: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .footer-signature {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: white;
        }
        
        .footer-contact {
            display: flex;
            justify-content: center;
            gap: 10px 50px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .footer-contact a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .footer-contact a:hover {
            opacity: 1;
        }
        
        .footer-copyright {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .footer-copyright p {
            margin: 0;
            font-size: 12px;
            opacity: 0.8;
        }
        
        .footer-copyright p:first-child {
            margin-bottom: 5px;
        }
        
        .divider {
            height: 2px;
            background: linear-gradient(90deg, #3498db, #2980b9, #3498db);
            margin: 30px 0;
            border-radius: 1px;
        }
        
        .closing-text {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .contact-text {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        
        /* Dark mode styles */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a1a1a !important;
                color: #e0e0e0 !important;
            }
            
            .container {
                background-color: #2d2d2d !important;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            }
            
            .content {
                background-color: #2d2d2d !important;
                color: #e0e0e0 !important;
            }
            
            .welcome-message {
                color: #e0e0e0 !important;
            }
            
            .section h2 {
                color: #ffffff !important;
            }
            
            .detail-row {
                border-bottom-color: #5d5d5d !important;
            }
            
            .detail-label {
                color: #e0e0e0 !important;
            }
            
            .detail-value {
                color: #e0e0e0 !important;
            }
            
            .reference {
                color: #e0e0e0 !important;
            }
            
            .next-steps {
                background: linear-gradient(135deg, #2d4d2d, #3d4d3d) !important;
            }
            
            .next-steps h3,
            .next-steps li {
                color: #e0e0e0 !important;
            }
            
            .closing-text,
            .contact-text {
                color: #e0e0e0 !important;
            }
        }
        
        /* Email client specific dark mode fixes */
        [data-ogsc] body,
        [data-ogsb] body,
        .dark-mode body,
        [data-dark-mode] body {
            background-color: #1a1a1a !important;
            color: #e0e0e0 !important;
        }
        
        [data-ogsc] .container,
        [data-ogsb] .container,
        .dark-mode .container,
        [data-dark-mode] .container {
            background-color: #2d2d2d !important;
        }
        
        [data-ogsc] .content,
        [data-ogsb] .content,
        .dark-mode .content,
        [data-dark-mode] .content {
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
        }
        
        [data-ogsc] .welcome-message,
        [data-ogsc] .detail-label,
        [data-ogsc] .detail-value,
        [data-ogsc] .next-steps h3,
        [data-ogsc] .next-steps li,
        [data-ogsc] .closing-text,
        [data-ogsc] .contact-text,
        [data-ogsb] .welcome-message,
        [data-ogsb] .detail-label,
        [data-ogsb] .detail-value,
        [data-ogsb] .next-steps h3,
        [data-ogsb] .next-steps li,
        [data-ogsb] .closing-text,
        [data-ogsb] .contact-text,
        .dark-mode .welcome-message,
        .dark-mode .detail-label,
        .dark-mode .detail-value,
        .dark-mode .next-steps h3,
        .dark-mode .next-steps li,
        .dark-mode .closing-text,
        .dark-mode .contact-text,
        [data-dark-mode] .welcome-message,
        [data-dark-mode] .detail-label,
        [data-dark-mode] .detail-value,
        [data-dark-mode] .next-steps h3,
        [data-dark-mode] .next-steps li,
        [data-dark-mode] .closing-text,
        [data-dark-mode] .contact-text {
            color: #e0e0e0 !important;
        }
        
        [data-ogsc] .section h2,
        [data-ogsb] .section h2,
        .dark-mode .section h2,
        [data-dark-mode] .section h2 {
            color: #ffffff !important;
        }
        
        [data-ogsc] .detail-row,
        [data-ogsb] .detail-row,
        .dark-mode .detail-row,
        [data-dark-mode] .detail-row {
            border-bottom-color: #5d5d5d !important;
        }
        
        [data-ogsc] .reference,
        [data-ogsb] .reference,
        .dark-mode .reference,
        [data-dark-mode] .reference {
            color: #e0e0e0 !important;
        }
        
        [data-ogsc] .next-steps,
        [data-ogsb] .next-steps,
        .dark-mode .next-steps,
        [data-dark-mode] .next-steps {
            background: linear-gradient(135deg, #2d4d2d, #3d4d3d) !important;
        }
        
        @media (max-width: 600px) {
            .container {
                margin: 0;
                border-radius: 0;
            }
            .content {
                padding: 30px 20px;
            }
            .header {
                padding: 30px 20px;
            }
            .detail-row {
                display: block;
            }
            .detail-label {
                display: block;
                width: 100%;
                padding-right: 0;
                margin-bottom: 5px;
            }
            .detail-value {
                display: block;
                width: 100%;
                text-align: left;
            }
            .footer-contact {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Investment Request Received</h1>
            <div class="subtitle">Admin Team Will Contact You Soon</div>
        </div>

        <div class="content">
            <div class="welcome-message">
                <strong>Dear {{ $user->name }},</strong><br><br>
                Thank you for your investment request with Ceylon Ayurveda Health. Our admin team has received your application and will contact you within 24 hours with detailed bank transfer instructions.
            </div>

            <div class="pending-highlight">
                <h3>📋 Request Under Review</h3>
                <p>Your investment request is being processed by our dedicated team</p>
            </div>

            <div class="section">
                <div class="section-header">
                    <h2>Investment Request Details</h2>
                </div>
                
                <div class="investment-details">
                    <div class="detail-row">
                        <span class="detail-label">Reference Number:</span>
                        <span class="detail-value">
                            <span class="reference">{{ $investment->reference }}</span>
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Investment Amount:</span>
                        <span class="detail-value amount">£{{ number_format($investment->amount, 2) }}</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Virtual Branch:</span>
                        <span class="detail-value">{{ $location->name }}, {{ $location->city }}</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Payment Method:</span>
                        <span class="detail-value">Bank Transfer</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="status-pending">⏳ Pending Admin Contact</span>
                        </span>
                    </div>
                    
                    @if($investment->notes)
                    <div class="detail-row">
                        <span class="detail-label">Your Notes:</span>
                        <span class="detail-value">{{ $investment->notes }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <div class="next-steps">
                <h3>🚀 Next Steps</h3>
                <ul>
                    <li>Our admin team will review your investment request thoroughly</li>
                    <li>You'll receive personalized bank transfer details within 24 hours</li>
                    <li>Complete the bank transfer using the provided account details</li>
                    <li>Your investment will be confirmed once payment is received and verified</li>
                    <li>You'll receive a confirmation email with your active investment details</li>
                </ul>
            </div>

            <div class="divider"></div>

            <p class="closing-text">
                We appreciate your interest in investing with Ceylon Ayurveda Health. Your investment will help expand our mission to bring digital Ayurvedic wellness to communities across the UK, making holistic healthcare more accessible and dignified.
            </p>

            <p class="contact-text">
                If you have any questions about your investment request or need any assistance, please don't hesitate to contact our support team. We're here to help make your investment process as smooth as possible.
            </p>
        </div>

        <div class="footer">
            <div class="footer-signature">
                With gratitude and excitement,<br>
                Ceylon Ayurveda Health Investment Team
            </div>
            
            <div class="footer-contact">
                <a href="mailto:info@ceylonayurvedahealth.com">📧 info@ceylonayurvedahealth.com</a>
                <a href="tel:+442071836484">📞 0207 183 6484</a>
                <a href="https://www.ceylonayurvedahealth.co.uk">🌐 www.ceylonayurvedahealth.co.uk</a>
            </div>
            
            <div class="footer-copyright">
                <p>&copy; {{ date('Y') }} Ceylon Ayurveda Health. All rights reserved.</p>
                <p>This email was sent regarding your investment request with reference {{ $investment->reference }}.</p>
            </div>
        </div>
    </div>
</body>
</html>