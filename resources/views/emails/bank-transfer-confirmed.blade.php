<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Ceylon Ayurveda Health - Bank Transfer Confirmed</title>
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
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
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
            border-bottom: 3px solid #27ae60;
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
        
        .status-confirmed {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .transfer-notes {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            padding: 25px;
            border-radius: 12px;
            margin: 30px 0;
            border-left: 4px solid #3498db;
        }
        
        .transfer-notes h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
        }
        
        .transfer-notes p {
            margin: 0;
            color: #2c3e50;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .confirmation-highlight {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin: 30px 0;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2);
        }
        
        .confirmation-highlight h3 {
            margin: 0 0 10px 0;
            font-size: 20px;
            font-weight: 600;
            color: white;
        }
        
        .confirmation-highlight p {
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
            background: linear-gradient(90deg, #27ae60, #2ecc71, #27ae60);
            margin: 30px 0;
            border-radius: 1px;
        }
        
        .closing-text {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .mission-text {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        
        .support-text {
            font-size: 16px;
            color: #2c3e50;
        }
        
        .welcome-text {
            text-align: center;
            font-size: 18px;
            color: #2c3e50;
            margin: 40px 0;
        }
        
        /* Dark mode styles */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a1a1a;
                color: #e0e0e0;
            }
            
            .container {
                background-color: #2d2d2d;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            }
            
            .content {
                background-color: #2d2d2d;
                color: #e0e0e0;
            }
            
            .welcome-message {
                color: #e0e0e0;
            }
            
            .section h2 {
                color: #ffffff;
            }
            
            .investment-details {
  
                border-color: #5d5d5d !important;
            }
            
            .detail-row {
                border-bottom-color: #5d5d5d;
            }
            
            .detail-label {
                color: #e0e0e0;
            }
            
            .detail-value {
                color: #e0e0e0;
            }
            
            .transfer-notes {
                background: linear-gradient(135deg, #2d3d4d, #3d2d4d);
            }
            
            .transfer-notes h4,
            .transfer-notes p {
                color: #e0e0e0;
            }
            
            .closing-text,
            .mission-text,
            .support-text,
            .welcome-text {
                color: #e0e0e0;
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
        [data-ogsc] .transfer-notes h4,
        [data-ogsc] .transfer-notes p,
        [data-ogsc] .closing-text,
        [data-ogsc] .mission-text,
        [data-ogsc] .support-text,
        [data-ogsc] .welcome-text,
        [data-ogsb] .welcome-message,
        [data-ogsb] .detail-label,
        [data-ogsb] .detail-value,
        [data-ogsb] .transfer-notes h4,
        [data-ogsb] .transfer-notes p,
        [data-ogsb] .closing-text,
        [data-ogsb] .mission-text,
        [data-ogsb] .support-text,
        [data-ogsb] .welcome-text,
        .dark-mode .welcome-message,
        .dark-mode .detail-label,
        .dark-mode .detail-value,
        .dark-mode .transfer-notes h4,
        .dark-mode .transfer-notes p,
        .dark-mode .closing-text,
        .dark-mode .mission-text,
        .dark-mode .support-text,
        .dark-mode .welcome-text,
        [data-dark-mode] .welcome-message,
        [data-dark-mode] .detail-label,
        [data-dark-mode] .detail-value,
        [data-dark-mode] .transfer-notes h4,
        [data-dark-mode] .transfer-notes p,
        [data-dark-mode] .closing-text,
        [data-dark-mode] .mission-text,
        [data-dark-mode] .support-text,
        [data-dark-mode] .welcome-text {
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
        
        [data-ogsc] .transfer-notes,
        [data-ogsb] .transfer-notes,
        .dark-mode .transfer-notes,
        [data-dark-mode] .transfer-notes {
            background: linear-gradient(135deg, #2d3d4d, #3d2d4d) !important;
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
            <h1>Bank Transfer Confirmed!</h1>
            <div class="subtitle">Your Investment is Now Active</div>
        </div>

        <div class="content">
            <div class="welcome-message">
                <strong>Dear {{ $user->name }},</strong><br><br>
                Excellent news! Your bank transfer investment has been successfully confirmed and processed. Your funds are now actively contributing to the growth of <strong>{{ $location->name }}</strong>.
            </div>

            <div class="confirmation-highlight">
                <h3>🎉 Investment Successfully Processed</h3>
                <p>Your contribution is now working to transform holistic wellness delivery across the UK</p>
            </div>

            <div class="section">
                <div class="section-header">
                    <h2>Investment Confirmation Details</h2>
                </div>
                
                <div class="investment-details">
                    <div class="detail-row">
                        <span class="detail-label">Reference Number:</span>
                        <span class="detail-value"><strong>{{ $reference }}</strong></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Investment Amount:</span>
                        <span class="detail-value amount">{{ $amount }}</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Virtual Branch:</span>
                        <span class="detail-value">{{ $location->name }}</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Investment Date:</span>
                        <span class="detail-value">{{ $investment->invested_at ? $investment->invested_at->format('F j, Y g:i A') : 'Processing' }}</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Confirmation Date:</span>
                        <span class="detail-value">{{ $confirmationDate }}</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Payment Method:</span>
                        <span class="detail-value">Bank Transfer</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="status-confirmed">✓ Confirmed</span>
                        </span>
                    </div>
                </div>
            </div>

            @if($investment->bank_transfer_details)
            <div class="transfer-notes">
                <h4>📋 Transfer Notes</h4>
                <p>{{ $investment->bank_transfer_details }}</p>
            </div>
            @endif

            <div class="divider"></div>

            <p class="closing-text">
                Thank you for choosing to invest with Ceylon Ayurveda Health. Your investment helps support the continued growth and success of {{ $location->name }}, bringing digital Ayurvedic wellness to communities across the UK.
            </p>

            <p class="mission-text">
                Your investment is now part of our mission to serve 3% of the UK's NHS outpatient workload by 2030, making holistic wellness accessible, dignified, and digitally delivered.
            </p>

            <p class="support-text">
                If you have any questions about your investment or need any assistance, please don't hesitate to contact our support team.
            </p>

            <p class="welcome-text">
                <strong>Welcome to the Ceylon Ayurveda Health investor community!</strong>
            </p>
        </div>

        <div class="footer">
            <div class="footer-signature">
                With gratitude and commitment,<br>
                Ceylon Ayurveda Health Investment Team
            </div>
            
            <div class="footer-contact">
                <a href="mailto:info@ceylonayurvedahealth.com">📧 info@ceylonayurvedahealth.com</a> <br>
                <a href="tel:+442071836484">📞 0207 183 6484</a><br>
                <a href="https://www.ceylonayurvedahealth.co.uk">🌐 www.ceylonayurvedahealth.co.uk</a>
            </div>
            
            <div class="footer-copyright">
                <p>&copy; {{ date('Y') }} Ceylon Ayurveda Health. All rights reserved.</p>
                <p>This email was sent regarding your investment with reference {{ $reference }}.</p>
            </div>
        </div>
    </div>
</body>
</html>