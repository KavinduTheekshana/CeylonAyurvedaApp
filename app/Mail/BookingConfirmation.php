<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The booking instance.
     *
     * @var \App\Models\Booking
     */
    public $booking;

    /**
     * The service instance.
     *
     * @var \App\Models\Service
     */
    public $service;
    public $therapist;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\Booking  $booking
     * @return void
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
        $this->service = $booking->service;
        $this->therapist = $booking->therapist; 
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Booking Confirmation: ' . $this->booking->reference)
            ->view('emails.booking-confirmation')
            ->with([
                'booking' => $this->booking,
                'service' => $this->service,
                'therapist' => $this->therapist, // ADD: Pass therapist to view
                'therapistName' => $this->therapist ? $this->therapist->name : 'To be assigned', // ADD: Helper variable
            ]);
    }
}