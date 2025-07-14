<?php

namespace App\Mail;

use App\Models\Investment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class BankTransferConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Investment $investment;
    public $user;
    public $location;

    public function __construct(Investment $investment)
    {
        Log::info('BankTransferConfirmedMail constructor called for investment ID: ' . $investment->id);
        Log::info('Investment All Data: ' . $investment->toJson());
        $this->investment = $investment;
        
        // Load relationships to avoid N+1 queries and ensure they're available
        $this->investment->load(['user', 'location']);
        
        // Set public properties for easier access in the view
        $this->user = $this->investment->user;
        Log::info('BankTransferConfirmedMail constructed for investment ID: ' . $this->investment->id);
        $this->location = $this->investment->location;
        Log::info('Location loaded for investment ID: ' . $this->investment->id);
    }

    public function build()
    {
        return $this->subject('Investment Confirmed - Bank Transfer Received')
                    ->view('emails.bank-transfer-confirmed')
                    ->with([
                        'investment' => $this->investment,
                        'user' => $this->user,
                        'location' => $this->location,
                        'amount' => '£' . number_format($this->investment->amount, 2),
                        'reference' => $this->investment->reference,
                        'confirmationDate' => $this->investment->bank_transfer_confirmed_at 
                            ? $this->investment->bank_transfer_confirmed_at->format('F j, Y g:i A') 
                            : now()->format('F j, Y g:i A'),
                    ]);
    }
}