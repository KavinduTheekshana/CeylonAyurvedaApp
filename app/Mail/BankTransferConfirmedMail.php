<?php

namespace App\Mail;

use App\Models\Investment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BankTransferConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Investment $investment;

    public function __construct(Investment $investment)
    {
        $this->investment = $investment;
    }

    public function build()
    {
        return $this->subject('Investment Confirmed - Bank Transfer Received')
                    ->view('emails.bank-transfer-confirmed')
                    ->with([
                        'investment' => $this->investment,
                        'user' => $this->investment->user,
                        'location' => $this->investment->location,
                    ]);
    }
}