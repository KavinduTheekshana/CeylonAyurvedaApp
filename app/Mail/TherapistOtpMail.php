<?php
namespace App\Mail;

use App\Models\Therapist;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TherapistOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $therapist;
    public $otp;

    public function __construct(Therapist $therapist, $otp)
    {
        $this->therapist = $therapist;
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('Verify Your Ceylon Ayurveda Health Account')
            ->view('emails.therapist-otp');
    }
}