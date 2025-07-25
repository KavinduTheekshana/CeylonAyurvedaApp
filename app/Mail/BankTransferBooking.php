<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BankTransferBooking extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking Request - Bank Transfer Details - ' . $this->booking->reference,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bank-transfer-booking',
            with: [
                'booking' => $this->booking,
                'service' => $this->booking->service,
                'therapist' => $this->booking->therapist,
                'bankDetails' => $this->getBankDetails(),
            ],
        );
    }

    /**
     * Get bank details from config or return default
     */
    private function getBankDetails(): array
    {
        return [
            'bank_name' => config('bank.name', 'HSBC'),
            'account_name' => config('bank.account_name', 'ROUTE ONE RECRUITMENT SERVICES LTD'),
            'account_number' => config('bank.account_number', '24161040'),
            'sort_code' => config('bank.sort_code', '04-06-05'),
            'reference' => $this->booking->reference,
        ];
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}