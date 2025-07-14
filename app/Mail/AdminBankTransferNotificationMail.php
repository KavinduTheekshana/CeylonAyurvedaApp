<?php

namespace App\Mail;

use App\Models\Investment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminBankTransferNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Investment $investment;

    public function __construct(Investment $investment)
    {
        $this->investment = $investment;
    }

    public function build()
    {
        return $this->subject('New Bank Transfer Investment Request')
                    ->view('emails.admin-bank-transfer-notification')
                    ->with([
                        'investment' => $this->investment,
                        'user' => $this->investment->user,
                        'location' => $this->investment->location,
                    ]);
    }
}