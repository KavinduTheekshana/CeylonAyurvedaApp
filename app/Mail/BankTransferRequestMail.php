<?php

namespace App\Mail;

use App\Models\Investment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BankTransferRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public Investment $investment;

    public function __construct(Investment $investment)
    {
        $this->investment = $investment;
    }

    public function build()
    {
        return $this->subject('Investment Request - Bank Transfer Details')
                    ->view('emails.bank-transfer-request')
                    ->with([
                        'investment' => $this->investment,
                        'user' => $this->investment->user,
                        'location' => $this->investment->location,
                    ]);
    }
}
