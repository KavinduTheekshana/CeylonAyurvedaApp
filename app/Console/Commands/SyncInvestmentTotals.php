<?php
// app/Console/Commands/SyncInvestmentTotals.php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\LocationInvestment;
use Illuminate\Console\Command;

class SyncInvestmentTotals extends Command
{
    protected $signature = 'investments:sync-totals {--location= : Sync specific location ID}';
    
    protected $description = 'Sync investment totals for all locations or a specific location';

    public function handle()
    {
        $locationId = $this->option('location');
        
        if ($locationId) {
            $this->syncLocationTotals($locationId);
        } else {
            $this->syncAllLocationTotals();
        }
        
        $this->info('Investment totals synced successfully!');
    }

    private function syncLocationTotals($locationId)
    {
        $location = Location::find($locationId);
        
        if (!$location) {
            $this->error("Location with ID {$locationId} not found.");
            return;
        }

        $this->updateLocationInvestmentTotals($location);
        $this->info("Synced totals for location: {$location->name}");
    }

    private function syncAllLocationTotals()
    {
        $locations = Location::with('locationInvestment')->get();
        
        $this->info("Syncing totals for {$locations->count()} locations...");
        
        $bar = $this->output->createProgressBar($locations->count());
        $bar->start();

        foreach ($locations as $location) {
            $this->updateLocationInvestmentTotals($location);
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
    }

    private function updateLocationInvestmentTotals(Location $location)
    {
        // Calculate actual totals from investments
        $totalInvested = $location->investments()
            ->where('status', 'completed')
            ->sum('amount');

        $totalInvestors = $location->investments()
            ->where('status', 'completed')
            ->distinct('user_id')
            ->count();

        // Create or update location investment record
        LocationInvestment::updateOrCreate(
            ['location_id' => $location->id],
            [
                'total_invested' => $totalInvested,
                'total_investors' => $totalInvestors,
                'investment_limit' => 10000, // Default limit
                'is_open_for_investment' => true,
            ]
        );
    }
}