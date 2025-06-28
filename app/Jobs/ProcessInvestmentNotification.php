<?php
namespace App\Jobs;

use App\Models\Investment;
use App\Mail\InvestmentConfirmation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessInvestmentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Investment $investment
    ) {}

    public function handle(): void
    {
        // Send confirmation email to investor
        Mail::to($this->investment->user->email)
            ->send(new InvestmentConfirmation($this->investment));
        
        // You could also send notifications to admins, SMS, etc.
    }
}