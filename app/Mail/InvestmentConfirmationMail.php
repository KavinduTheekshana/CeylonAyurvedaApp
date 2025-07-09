<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Investment;
use App\Models\Location;

class InvestmentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $investment;
    public $location;
    public $user;

    public function __construct(Investment $investment)
    {
        $this->investment = $investment;
        $this->location = $investment->location;
        $this->user = $investment->user;
    }

    public function build()
    {
        return $this->subject('Investment Confirmation - ' . $this->investment->reference)
                    ->view('emails.investment-confirmation')
                    ->with([
                        'investment' => $this->investment,
                        'location' => $this->location,
                        'user' => $this->user,
                        'amount' => '£' . number_format($this->investment->amount, 2),
                        'reference' => $this->investment->reference,
                    ]);
    }
}
