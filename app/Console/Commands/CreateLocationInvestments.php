<?php
namespace App\Console\Commands;

use App\Models\Location;
use App\Models\LocationInvestment;
use Illuminate\Console\Command;

class CreateLocationInvestments extends Command
{
    protected $signature = 'investments:create-location-records';
    protected $description = 'Create investment records for existing locations';

    public function handle()
    {
        $locations = Location::whereDoesntHave('locationInvestment')->get();
        
        foreach ($locations as $location) {
            LocationInvestment::create([
                'location_id' => $location->id,
                'total_invested' => 0,
                'investment_limit' => 10000,
                'total_investors' => 0,
                'is_open_for_investment' => true,
            ]);
            
            $this->info("Created investment record for location: {$location->name}");
        }
        
        $this->info("Completed creating investment records for {$locations->count()} locations");
    }
}