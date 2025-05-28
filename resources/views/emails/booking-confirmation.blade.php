<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #9A563A;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .booking-details {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #9A563A;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .detail-value {
            color: #333;
        }
        .therapist-info {
            background-color: #FFF8F0;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border: 1px solid #FFE4B5;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Booking Confirmation</h1>
        <p>Thank you for your booking!</p>
    </div>

    <div class="content">
        <h2>Hello {{ $booking->name }},</h2>
        
        <p>Your appointment has been successfully booked. Here are the details:</p>

        <div class="booking-details">
            <h3>Booking Details</h3>
            
            <div class="detail-row">
                <span class="detail-label">Reference Number:</span>
                <span class="detail-value">{{ $booking->reference }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Service:</span>
                <span class="detail-value">{{ $service->title }}</span>
            </div>
            
            <!-- NEW: Display Therapist Information -->
            @if($therapist)
            <div class="detail-row">
                <span class="detail-label">Therapist:</span>
                <span class="detail-value">{{ $therapist->name }}</span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value">{{ \Carbon\Carbon::parse($booking->date)->format('l, F j, Y') }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Time:</span>
                <span class="detail-value">{{ \Carbon\Carbon::parse($booking->time)->format('g:i A') }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Duration:</span>
                <span class="detail-value">{{ $service->duration }} minutes</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Price:</span>
                <span class="detail-value">£{{ number_format($booking->price, 2) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">{{ ucfirst($booking->status) }}</span>
            </div>
        </div>

        <!-- NEW: Special section for therapist information -->
        @if($therapist)
        <div class="therapist-info">
            <h4>Your Therapist</h4>
            <p><strong>{{ $therapist->name }}</strong> will be providing your {{ $service->title }} service.</p>
            @if($therapist->bio)
                <p>{{ $therapist->bio }}</p>
            @endif
            <p><em>Your Therapist arrive 10 minutes early for your appointment.</em></p>
        </div>
        @else
        <div class="therapist-info">
            <h4>Therapist Assignment</h4>
            <p>Your therapist will be assigned and you'll be notified before your appointment.</p>
        </div>
        @endif

        <div class="booking-details">
            <h3>Appointment Location</h3>
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
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">{{ $booking->email }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone:</span>
                <span class="detail-value">{{ $booking->phone }}</span>
            </div>
        </div>

        <p><strong>Important:</strong> If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.</p>
        
        <p>We look forward to seeing you!</p>
    </div>

    <div class="footer">
        <p>This is an automated email. Please do not reply to this message.</p>
        <p>&copy; {{ date('Y') }} Your Company Name. All rights reserved.</p>
    </div>
</body>
</html>