<?php
// app/Console/Commands/LockExpiredTreatmentHistories.php
// Run this command daily to automatically lock treatment histories after 24 hours

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TreatmentHistory;
use Carbon\Carbon;

class LockExpiredTreatmentHistories extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'treatment-history:lock-expired';

    /**
     * The console command description.
     */
    protected $description = 'Lock treatment histories that are past the 24-hour edit deadline';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired treatment histories...');

        $expiredHistories = TreatmentHistory::where('is_editable', true)
            ->where('edit_deadline_at', '<', Carbon::now())
            ->get();

        if ($expiredHistories->isEmpty()) {
            $this->info('No expired treatment histories found.');
            return 0;
        }

        $count = $expiredHistories->count();
        
        // Update all expired records
        TreatmentHistory::where('is_editable', true)
            ->where('edit_deadline_at', '<', Carbon::now())
            ->update(['is_editable' => false]);

        $this->info("Successfully locked {$count} expired treatment histories.");
        
        return 0;
    }
}

// Add to app/Console/Kernel.php in the schedule method:
/*
protected function schedule(Schedule $schedule)
{
    // Run daily at midnight to lock expired treatment histories
    $schedule->command('treatment-history:lock-expired')->dailyAt('00:01');
}
*/